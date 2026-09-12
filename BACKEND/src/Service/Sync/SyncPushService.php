<?php

namespace App\Service\Sync;

use App\DTO\Clinique\CreateConsultationInput;
use App\DTO\Clinique\CreateVisiteInput;
use App\DTO\Clinique\UpdateConsultationInput;
use App\DTO\Patient\CreatePatientInput;
use App\DTO\Pharmacie\AnnulerVenteInput;
use App\DTO\Pharmacie\CreateAjustementInput;
use App\DTO\Pharmacie\CreateFamilleMedicamentInput;
use App\DTO\Pharmacie\CreateFournisseurInput;
use App\DTO\Pharmacie\CreateMedicamentInput;
use App\DTO\Pharmacie\CreateUniteMedicamentInput;
use App\DTO\Pharmacie\DemandeServiceLigneInput;
use App\DTO\Pharmacie\ReceptionLigneInput;
use App\DTO\Pharmacie\RefuserDemandeInput;
use App\DTO\Pharmacie\ReglerDemandeInput;
use App\DTO\Pharmacie\UpdateLotInput;
use App\DTO\Pharmacie\UpdateFamilleMedicamentInput;
use App\DTO\Pharmacie\UpdateFournisseurInput;
use App\DTO\Pharmacie\UpdateMedicamentInput;
use App\DTO\Pharmacie\UpdateUniteMedicamentInput;
use App\DTO\Pharmacie\UpsertDemandeServiceInput;
use App\DTO\Pharmacie\UpsertReceptionInput;
use App\DTO\Pharmacie\UpsertVenteInput;
use App\DTO\Pharmacie\VenteLigneInput;
use App\Entity\Lot;
use App\Entity\Personnel;
use App\Entity\SyncMutation;
use App\Exception\ConflictException;
use App\Exception\NotFoundException;
use App\Repository\SyncMutationRepository;
use App\Service\Clinique\ConsultationService;
use App\Service\Clinique\VisiteService;
use App\Service\Patient\PatientService;
use App\Service\Pharmacie\AjustementService;
use App\Service\Pharmacie\DemandeServiceService;
use App\Service\Pharmacie\FamilleMedicamentService;
use App\Service\Pharmacie\FournisseurService;
use App\Service\Pharmacie\LotService;
use App\Service\Pharmacie\MedicamentService;
use App\Service\Pharmacie\ReceptionService;
use App\Service\Pharmacie\UniteMedicamentService;
use App\Util\CalendarDate;
use App\Service\Pharmacie\VenteService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\Validator\Exception\ValidationFailedException;

final class SyncPushService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly SyncMutationRepository $syncMutationRepository,
        private readonly VenteService $venteService,
        private readonly DemandeServiceService $demandeServiceService,
        private readonly ReceptionService $receptionService,
        private readonly AjustementService $ajustementService,
        private readonly MedicamentService $medicamentService,
        private readonly UniteMedicamentService $uniteMedicamentService,
        private readonly FamilleMedicamentService $familleMedicamentService,
        private readonly FournisseurService $fournisseurService,
        private readonly LotService $lotService,
        private readonly PatientService $patientService,
        private readonly VisiteService $visiteService,
        private readonly ConsultationService $consultationService,
        private readonly Security $security,
    ) {
    }

    /**
     * @param list<array<string, mixed>> $mutations
     * @return list<array<string, mixed>>
     */
    public function push(array $mutations): array
    {
        $results = [];
        foreach ($mutations as $mutation) {
            $results[] = $this->process($mutation);
        }

        return $results;
    }

    /**
     * @param array<string, mixed> $mutation
     * @return array<string, mixed>
     */
    private function process(array $mutation): array
    {
        $clientId = trim((string) ($mutation['clientId'] ?? ''));
        $action = trim((string) ($mutation['action'] ?? ''));
        $module = trim((string) ($mutation['module'] ?? ''));
        $payload = is_array($mutation['payload'] ?? null) ? $mutation['payload'] : [];

        if ('' === $clientId || '' === $action) {
            return [
                'clientId' => $clientId,
                'status' => SyncMutation::STATUS_REJECTED,
                'message' => 'clientId et action sont obligatoires.',
            ];
        }

        $existing = $this->syncMutationRepository->findOneByClientId($clientId);
        if (null !== $existing && SyncMutation::STATUS_ACCEPTED === $existing->getStatus()) {
            return $this->serializeStored($existing);
        }

        $record = $existing ?? (new SyncMutation())
            ->setClientId($clientId)
            ->setModule('' !== $module ? $module : $this->moduleFromAction($action))
            ->setAction($action)
            ->setCreatedAt(new \DateTimeImmutable())
            ->setCreatedBy($this->currentPersonnel());
        if (null !== $existing) {
            $record
                ->setModule('' !== $module ? $module : $record->getModule())
                ->setAction($action)
                ->setCreatedBy($this->currentPersonnel());
        }

        try {
            [$entityType, $entityId, $data] = $this->dispatch($action, $payload);
            $record
                ->setStatus(SyncMutation::STATUS_ACCEPTED)
                ->setEntityType($entityType)
                ->setEntityId($entityId)
                ->setResultJson(json_encode($data, JSON_THROW_ON_ERROR));
        } catch (ConflictException $exception) {
            $record
                ->setStatus(SyncMutation::STATUS_CONFLICT)
                ->setResultJson(json_encode(['message' => $exception->getMessage()], JSON_THROW_ON_ERROR));
        } catch (NotFoundException|AccessDeniedHttpException|ValidationFailedException|\InvalidArgumentException $exception) {
            $message = $exception instanceof ValidationFailedException
                ? (string) $exception->getViolations()
                : $exception->getMessage();
            $record
                ->setStatus(SyncMutation::STATUS_REJECTED)
                ->setResultJson(json_encode(['message' => $message], JSON_THROW_ON_ERROR));
        }

        if (!$this->entityManager->isOpen()) {
            $result = $record->getResult() ?? [];

            return [
                'clientId' => $clientId,
                'status' => $record->getStatus() ?? SyncMutation::STATUS_CONFLICT,
                'action' => $action,
                'entityType' => $record->getEntityType(),
                'entityId' => $record->getEntityId(),
                'message' => $result['message'] ?? 'EntityManager fermé après conflit.',
                'data' => null,
            ];
        }

        $this->entityManager->persist($record);
        $this->entityManager->flush();

        return $this->serializeStored($record);
    }

    /**
     * @param array<string, mixed> $payload
     * @return array{0: string, 1: string, 2: array<string, mixed>}
     */
    private function dispatch(string $action, array $payload): array
    {
        return match ($action) {
            'pharmacie.vente.create' => $this->venteCreate($payload, false),
            'pharmacie.vente.complete', 'pharmacie.vente.create_and_valider' => $this->venteCreate($payload, true),
            'pharmacie.vente.update' => $this->venteUpdate($payload),
            'pharmacie.vente.valider' => $this->venteValider($payload),
            'pharmacie.vente.annuler' => $this->venteAnnuler($payload),
            'pharmacie.vente.delete' => $this->venteDelete($payload),
            'pharmacie.demande_service.create' => $this->demandeCreate($payload),
            'pharmacie.demande_service.update' => $this->demandeUpdate($payload),
            'pharmacie.demande_service.envoyer' => $this->demandeEnvoyer($payload),
            'pharmacie.demande_service.delivrer' => $this->demandeDelivrer($payload),
            'pharmacie.demande_service.refuser' => $this->demandeRefuser($payload),
            'pharmacie.demande_service.regler' => $this->demandeRegler($payload),
            'pharmacie.demande_service.delete' => $this->demandeDelete($payload),
            'pharmacie.reception.create' => $this->receptionCreate($payload),
            'pharmacie.reception.update' => $this->receptionUpdate($payload),
            'pharmacie.reception.valider' => $this->receptionValider($payload),
            'pharmacie.reception.delete' => $this->receptionDelete($payload),
            'pharmacie.ajustement.create' => $this->ajustementCreate($payload),
            'pharmacie.medicament.create' => $this->medicamentCreate($payload),
            'pharmacie.medicament.update' => $this->medicamentUpdate($payload),
            'pharmacie.medicament.delete' => $this->medicamentDelete($payload),
            'pharmacie.unite.create' => $this->uniteCreate($payload),
            'pharmacie.unite.update' => $this->uniteUpdate($payload),
            'pharmacie.unite.delete' => $this->uniteDelete($payload),
            'pharmacie.famille.create' => $this->familleCreate($payload),
            'pharmacie.famille.update' => $this->familleUpdate($payload),
            'pharmacie.famille.delete' => $this->familleDelete($payload),
            'pharmacie.fournisseur.create' => $this->fournisseurCreate($payload),
            'pharmacie.fournisseur.update' => $this->fournisseurUpdate($payload),
            'pharmacie.fournisseur.delete' => $this->fournisseurDelete($payload),
            'pharmacie.lot.update' => $this->lotUpdate($payload),
            'patient.create' => $this->patientCreate($payload),
            'clinique.visite.create' => $this->visiteCreate($payload),
            'clinique.consultation.create' => $this->consultationCreate($payload),
            'clinique.consultation.update' => $this->consultationUpdate($payload),
            default => throw new \InvalidArgumentException('Action de sync inconnue : ' . $action),
        };
    }

    /**
     * @param array<string, mixed> $payload
     * @return array{0: string, 1: string, 2: array<string, mixed>}
     */
    private function venteCreate(array $payload, bool $valider): array
    {
        $input = $this->venteInput($payload);
        $vente = $valider
            ? $this->venteService->createAndValider($input)
            : $this->venteService->create($input);

        return ['vente', (string) $vente->getId(), $this->venteService->serializeDetail($vente)];
    }

    /**
     * @param array<string, mixed> $payload
     * @return array{0: string, 1: string, 2: array<string, mixed>}
     */
    private function venteValider(array $payload): array
    {
        $id = (int) ($payload['id'] ?? 0);
        if ($id <= 0) {
            throw new \InvalidArgumentException('Identifiant de vente manquant.');
        }
        $vente = $this->venteService->valider($id);

        return ['vente', (string) $vente->getId(), $this->venteService->serializeDetail($vente)];
    }

    /**
     * @param array<string, mixed> $payload
     * @return array{0: string, 1: string, 2: array<string, mixed>}
     */
    private function venteUpdate(array $payload): array
    {
        $id = $this->requireServerId($payload['id'] ?? 0, 'Vente');
        $vente = $this->venteService->update($id, $this->venteInput($payload));

        return ['vente', (string) $vente->getId(), $this->venteService->serializeDetail($vente)];
    }

    /**
     * @param array<string, mixed> $payload
     * @return array{0: string, 1: string, 2: array<string, mixed>}
     */
    private function venteAnnuler(array $payload): array
    {
        $id = $this->requireServerId($payload['id'] ?? 0, 'Vente');
        $vente = $this->venteService->annuler($id, new AnnulerVenteInput(
            motif: isset($payload['motif']) ? (string) $payload['motif'] : null,
        ));

        return ['vente', (string) $vente->getId(), $this->venteService->serializeDetail($vente)];
    }

    /**
     * @param array<string, mixed> $payload
     * @return array{0: string, 1: string, 2: array<string, mixed>}
     */
    private function venteDelete(array $payload): array
    {
        $id = $this->requireServerId($payload['id'] ?? 0, 'Vente');
        $this->venteService->delete($id);

        return ['vente', (string) $id, ['id' => $id, 'deleted' => true]];
    }

    /**
     * @param array<string, mixed> $payload
     * @return array{0: string, 1: string, 2: array<string, mixed>}
     */
    private function demandeCreate(array $payload): array
    {
        $demande = $this->demandeServiceService->create($this->demandeInput($payload));

        return ['demande_service', (string) $demande->getId(), $this->demandeServiceService->serializeDetail($demande)];
    }

    /**
     * @param array<string, mixed> $payload
     * @return array{0: string, 1: string, 2: array<string, mixed>}
     */
    private function demandeUpdate(array $payload): array
    {
        $id = $this->requireServerId($payload['id'] ?? 0, 'Demande');
        $demande = $this->demandeServiceService->update($id, $this->demandeInput($payload));

        return ['demande_service', (string) $demande->getId(), $this->demandeServiceService->serializeDetail($demande)];
    }

    /**
     * @param array<string, mixed> $payload
     * @return array{0: string, 1: string, 2: array<string, mixed>}
     */
    private function demandeEnvoyer(array $payload): array
    {
        $demande = $this->demandeServiceService->envoyer($this->requireServerId($payload['id'] ?? 0, 'Demande'));

        return ['demande_service', (string) $demande->getId(), $this->demandeServiceService->serializeDetail($demande)];
    }

    /**
     * @param array<string, mixed> $payload
     * @return array{0: string, 1: string, 2: array<string, mixed>}
     */
    private function demandeDelivrer(array $payload): array
    {
        $demande = $this->demandeServiceService->delivrer($this->requireServerId($payload['id'] ?? 0, 'Demande'));

        return ['demande_service', (string) $demande->getId(), $this->demandeServiceService->serializeDetail($demande)];
    }

    /**
     * @param array<string, mixed> $payload
     * @return array{0: string, 1: string, 2: array<string, mixed>}
     */
    private function demandeRefuser(array $payload): array
    {
        $demande = $this->demandeServiceService->refuser(
            $this->requireServerId($payload['id'] ?? 0, 'Demande'),
            new RefuserDemandeInput(motif: (string) ($payload['motif'] ?? '')),
        );

        return ['demande_service', (string) $demande->getId(), $this->demandeServiceService->serializeDetail($demande)];
    }

    /**
     * @param array<string, mixed> $payload
     * @return array{0: string, 1: string, 2: array<string, mixed>}
     */
    private function demandeRegler(array $payload): array
    {
        $demande = $this->demandeServiceService->regler(
            $this->requireServerId($payload['id'] ?? 0, 'Demande'),
            new ReglerDemandeInput(modePaiement: (string) ($payload['modePaiement'] ?? 'ESPECES')),
        );

        return ['demande_service', (string) $demande->getId(), $this->demandeServiceService->serializeDetail($demande)];
    }

    /**
     * @param array<string, mixed> $payload
     * @return array{0: string, 1: string, 2: array<string, mixed>}
     */
    private function demandeDelete(array $payload): array
    {
        $id = $this->requireServerId($payload['id'] ?? 0, 'Demande');
        $this->demandeServiceService->delete($id);

        return ['demande_service', (string) $id, ['id' => $id, 'deleted' => true]];
    }

    /**
     * @param array<string, mixed> $payload
     * @return array{0: string, 1: string, 2: array<string, mixed>}
     */
    private function receptionCreate(array $payload): array
    {
        $reception = $this->receptionService->create($this->receptionInput($payload));

        return ['reception', (string) $reception->getId(), $this->receptionService->serializeDetail($reception)];
    }

    /**
     * @param array<string, mixed> $payload
     * @return array{0: string, 1: string, 2: array<string, mixed>}
     */
    private function receptionUpdate(array $payload): array
    {
        $id = $this->requireServerId($payload['id'] ?? 0, 'Réception');
        $reception = $this->receptionService->update($id, $this->receptionInput($payload));

        return ['reception', (string) $reception->getId(), $this->receptionService->serializeDetail($reception)];
    }

    /**
     * @param array<string, mixed> $payload
     * @return array{0: string, 1: string, 2: array<string, mixed>}
     */
    private function receptionValider(array $payload): array
    {
        $reception = $this->receptionService->valider($this->requireServerId($payload['id'] ?? 0, 'Réception'));

        return ['reception', (string) $reception->getId(), $this->receptionService->serializeDetail($reception)];
    }

    /**
     * @param array<string, mixed> $payload
     * @return array{0: string, 1: string, 2: array<string, mixed>}
     */
    private function receptionDelete(array $payload): array
    {
        $id = $this->requireServerId($payload['id'] ?? 0, 'Réception');
        $this->receptionService->delete($id);

        return ['reception', (string) $id, ['id' => $id, 'deleted' => true]];
    }

    /**
     * @param array<string, mixed> $payload
     * @return array{0: string, 1: string, 2: array<string, mixed>}
     */
    private function ajustementCreate(array $payload): array
    {
        $data = $this->ajustementService->create(new CreateAjustementInput(
            lotId: (int) ($payload['lotId'] ?? 0),
            type: (string) ($payload['type'] ?? 'AJUSTEMENT_MOINS'),
            quantite: (int) ($payload['quantite'] ?? 0),
            motif: (string) ($payload['motif'] ?? ''),
        ));

        return ['ajustement', (string) ($data['id'] ?? 0), $data];
    }

    /**
     * @param array<string, mixed> $payload
     * @return array{0: string, 1: string, 2: array<string, mixed>}
     */
    private function medicamentCreate(array $payload): array
    {
        $medicament = $this->medicamentService->create(new CreateMedicamentInput(
            code: (string) ($payload['code'] ?? ''),
            libelle: (string) ($payload['libelle'] ?? ''),
            dci: $this->strOrNull($payload['dci'] ?? null),
            forme: $this->strOrNull($payload['forme'] ?? null),
            dosage: $this->strOrNull($payload['dosage'] ?? null),
            uniteId: (int) ($payload['uniteId'] ?? 0),
            familleId: (int) ($payload['familleId'] ?? 0),
            prixVente: (string) ($payload['prixVente'] ?? '0'),
            seuilAlerte: (int) ($payload['seuilAlerte'] ?? 0),
            statut: (string) ($payload['statut'] ?? 'ACTIF'),
        ));

        return ['medicament', (string) $medicament->getId(), $this->medicamentService->serializeSummary($medicament)];
    }

    /**
     * @param array<string, mixed> $payload
     * @return array{0: string, 1: string, 2: array<string, mixed>}
     */
    private function medicamentUpdate(array $payload): array
    {
        $id = $this->requireServerId($payload['id'] ?? 0, 'Médicament');
        $medicament = $this->medicamentService->update($id, new UpdateMedicamentInput(
            libelle: (string) ($payload['libelle'] ?? ''),
            dci: $this->strOrNull($payload['dci'] ?? null),
            forme: $this->strOrNull($payload['forme'] ?? null),
            dosage: $this->strOrNull($payload['dosage'] ?? null),
            uniteId: (int) ($payload['uniteId'] ?? 0),
            familleId: (int) ($payload['familleId'] ?? 0),
            prixVente: (string) ($payload['prixVente'] ?? '0'),
            seuilAlerte: (int) ($payload['seuilAlerte'] ?? 0),
            statut: (string) ($payload['statut'] ?? 'ACTIF'),
        ));

        return ['medicament', (string) $medicament->getId(), $this->medicamentService->serializeSummary($medicament)];
    }

    /**
     * @param array<string, mixed> $payload
     * @return array{0: string, 1: string, 2: array<string, mixed>}
     */
    private function medicamentDelete(array $payload): array
    {
        $id = $this->requireServerId($payload['id'] ?? 0, 'Médicament');
        $this->medicamentService->delete($id);

        return ['medicament', (string) $id, ['id' => $id, 'deleted' => true]];
    }

    /**
     * @param array<string, mixed> $payload
     * @return array{0: string, 1: string, 2: array<string, mixed>}
     */
    private function uniteCreate(array $payload): array
    {
        $unite = $this->uniteMedicamentService->create(new CreateUniteMedicamentInput(
            code: (string) ($payload['code'] ?? ''),
            libelle: (string) ($payload['libelle'] ?? ''),
            ordre: (int) ($payload['ordre'] ?? 0),
            statut: (string) ($payload['statut'] ?? 'ACTIF'),
        ));

        return ['unite', (string) $unite->getId(), $this->uniteMedicamentService->serializeSummary($unite)];
    }

    /**
     * @param array<string, mixed> $payload
     * @return array{0: string, 1: string, 2: array<string, mixed>}
     */
    private function uniteUpdate(array $payload): array
    {
        $id = $this->requireServerId($payload['id'] ?? 0, 'Unité');
        $unite = $this->uniteMedicamentService->update($id, new UpdateUniteMedicamentInput(
            libelle: (string) ($payload['libelle'] ?? ''),
            ordre: (int) ($payload['ordre'] ?? 0),
            statut: (string) ($payload['statut'] ?? 'ACTIF'),
        ));

        return ['unite', (string) $unite->getId(), $this->uniteMedicamentService->serializeSummary($unite)];
    }

    /**
     * @param array<string, mixed> $payload
     * @return array{0: string, 1: string, 2: array<string, mixed>}
     */
    private function uniteDelete(array $payload): array
    {
        $id = $this->requireServerId($payload['id'] ?? 0, 'Unité');
        $this->uniteMedicamentService->delete($id);

        return ['unite', (string) $id, ['id' => $id, 'deleted' => true]];
    }

    /**
     * @param array<string, mixed> $payload
     * @return array{0: string, 1: string, 2: array<string, mixed>}
     */
    private function familleCreate(array $payload): array
    {
        $famille = $this->familleMedicamentService->create(new CreateFamilleMedicamentInput(
            code: (string) ($payload['code'] ?? ''),
            libelle: (string) ($payload['libelle'] ?? ''),
            ordre: (int) ($payload['ordre'] ?? 0),
            statut: (string) ($payload['statut'] ?? 'ACTIF'),
        ));

        return ['famille', (string) $famille->getId(), $this->familleMedicamentService->serializeSummary($famille)];
    }

    /**
     * @param array<string, mixed> $payload
     * @return array{0: string, 1: string, 2: array<string, mixed>}
     */
    private function familleUpdate(array $payload): array
    {
        $id = $this->requireServerId($payload['id'] ?? 0, 'Famille');
        $famille = $this->familleMedicamentService->update($id, new UpdateFamilleMedicamentInput(
            libelle: (string) ($payload['libelle'] ?? ''),
            ordre: (int) ($payload['ordre'] ?? 0),
            statut: (string) ($payload['statut'] ?? 'ACTIF'),
        ));

        return ['famille', (string) $famille->getId(), $this->familleMedicamentService->serializeSummary($famille)];
    }

    /**
     * @param array<string, mixed> $payload
     * @return array{0: string, 1: string, 2: array<string, mixed>}
     */
    private function familleDelete(array $payload): array
    {
        $id = $this->requireServerId($payload['id'] ?? 0, 'Famille');
        $this->familleMedicamentService->delete($id);

        return ['famille', (string) $id, ['id' => $id, 'deleted' => true]];
    }

    /**
     * @param array<string, mixed> $payload
     * @return array{0: string, 1: string, 2: array<string, mixed>}
     */
    private function fournisseurCreate(array $payload): array
    {
        $fournisseur = $this->fournisseurService->create(new CreateFournisseurInput(
            code: (string) ($payload['code'] ?? ''),
            libelle: (string) ($payload['libelle'] ?? ''),
            telephone: $this->strOrNull($payload['telephone'] ?? null),
            adresse: $this->strOrNull($payload['adresse'] ?? null),
            statut: (string) ($payload['statut'] ?? 'ACTIF'),
        ));

        return ['fournisseur', (string) $fournisseur->getId(), $this->fournisseurService->serializeSummary($fournisseur)];
    }

    /**
     * @param array<string, mixed> $payload
     * @return array{0: string, 1: string, 2: array<string, mixed>}
     */
    private function fournisseurUpdate(array $payload): array
    {
        $id = $this->requireServerId($payload['id'] ?? 0, 'Fournisseur');
        $fournisseur = $this->fournisseurService->update($id, new UpdateFournisseurInput(
            libelle: (string) ($payload['libelle'] ?? ''),
            telephone: $this->strOrNull($payload['telephone'] ?? null),
            adresse: $this->strOrNull($payload['adresse'] ?? null),
            statut: (string) ($payload['statut'] ?? 'ACTIF'),
        ));

        return ['fournisseur', (string) $fournisseur->getId(), $this->fournisseurService->serializeSummary($fournisseur)];
    }

    /**
     * @param array<string, mixed> $payload
     * @return array{0: string, 1: string, 2: array<string, mixed>}
     */
    private function fournisseurDelete(array $payload): array
    {
        $id = $this->requireServerId($payload['id'] ?? 0, 'Fournisseur');
        $this->fournisseurService->delete($id);

        return ['fournisseur', (string) $id, ['id' => $id, 'deleted' => true]];
    }

    /**
     * @param array<string, mixed> $payload
     * @return array{0: string, 1: string, 2: array<string, mixed>}
     */
    private function lotUpdate(array $payload): array
    {
        $id = $this->requireServerId($payload['id'] ?? 0, 'Lot');
        $lot = $this->lotService->update($id, new UpdateLotInput(
            numeroLot: (string) ($payload['numeroLot'] ?? ''),
            datePeremption: (string) ($payload['datePeremption'] ?? ''),
        ));

        return ['lot', (string) $lot->getId(), $this->lotService->serializeSummary($lot)];
    }

    /**
     * @param array<string, mixed> $payload
     * @return array{0: string, 1: string, 2: array<string, mixed>}
     */
    private function patientCreate(array $payload): array
    {
        $input = new CreatePatientInput(
            nom: (string) ($payload['nom'] ?? ''),
            postNom: (string) ($payload['postNom'] ?? ''),
            prenom: isset($payload['prenom']) ? (string) $payload['prenom'] : null,
            telephone: isset($payload['telephone']) ? (string) $payload['telephone'] : null,
            adresse: isset($payload['adresse']) ? (string) $payload['adresse'] : null,
            lieuNaissance: isset($payload['lieuNaissance']) ? (string) $payload['lieuNaissance'] : null,
            dateNaissance: (string) ($payload['dateNaissance'] ?? ''),
            sexe: (string) ($payload['sexe'] ?? ''),
            groupeSanguin: isset($payload['groupeSanguin']) ? (string) $payload['groupeSanguin'] : null,
            personneAprevenir: isset($payload['personneAprevenir']) ? (string) $payload['personneAprevenir'] : null,
            contactAPrevenir: isset($payload['contactAPrevenir']) ? (string) $payload['contactAPrevenir'] : null,
            status: (string) ($payload['status'] ?? 'ACTIF'),
        );
        $patient = $this->patientService->create($input);

        return ['patient', (string) $patient->getId(), $this->patientService->serializeDetail($patient)];
    }

    /**
     * @param array<string, mixed> $payload
     * @return array{0: string, 1: string, 2: array<string, mixed>}
     */
    private function visiteCreate(array $payload): array
    {
        $signes = is_array($payload['signesVitaux'] ?? null) ? $payload['signesVitaux'] : [];
        $input = new CreateVisiteInput(
            dpiId: (int) ($payload['dpiId'] ?? 0),
            serviceId: (int) ($payload['serviceId'] ?? 0),
            typeEntree: (string) ($payload['typeEntree'] ?? 'CONSULTATION'),
            motif: (string) ($payload['motif'] ?? ''),
            priorite: isset($payload['priorite']) ? (int) $payload['priorite'] : null,
            signesVitaux: $signes,
            sortedPrevuAt: isset($payload['sortedPrevuAt']) ? (string) $payload['sortedPrevuAt'] : null,
        );
        $visite = $this->visiteService->create($input);

        return ['visite', (string) $visite->getId(), $this->visiteService->serializeSummary($visite)];
    }

    /**
     * @param array<string, mixed> $payload
     * @return array{0: string, 1: string, 2: array<string, mixed>}
     */
    private function consultationCreate(array $payload): array
    {
        $input = new CreateConsultationInput(
            visiteId: isset($payload['visiteId']) ? (int) $payload['visiteId'] : null,
            typeConsultation: isset($payload['typeConsultation']) ? (string) $payload['typeConsultation'] : null,
            motif: isset($payload['motif']) ? (string) $payload['motif'] : null,
            statut: isset($payload['statut']) ? (string) $payload['statut'] : null,
        );
        $consultation = $this->consultationService->create($input);

        return ['consultation', (string) $consultation->getId(), $this->consultationService->serializeSummary($consultation)];
    }

    /**
     * @param array<string, mixed> $payload
     * @return array{0: string, 1: string, 2: array<string, mixed>}
     */
    private function consultationUpdate(array $payload): array
    {
        $id = (int) ($payload['id'] ?? 0);
        if ($id <= 0) {
            throw new \InvalidArgumentException('Identifiant de consultation manquant.');
        }

        $input = new UpdateConsultationInput(
            typeConsultation: isset($payload['typeConsultation']) ? (string) $payload['typeConsultation'] : null,
            motif: isset($payload['motif']) ? (string) $payload['motif'] : null,
            histoireMaladie: isset($payload['histoireMaladie']) ? (string) $payload['histoireMaladie'] : null,
            physicalExamText: isset($payload['physicalExamText']) ? (string) $payload['physicalExamText'] : null,
            conduireATenir: isset($payload['conduireATenir']) ? (string) $payload['conduireATenir'] : null,
            complementAnamnese: is_array($payload['complementAnamnese'] ?? null) ? $payload['complementAnamnese'] : null,
            physicalExam: is_array($payload['physicalExam'] ?? null) ? $payload['physicalExam'] : null,
            evolutionSheet: is_array($payload['evolutionSheet'] ?? null) ? $payload['evolutionSheet'] : null,
            statut: isset($payload['statut']) ? (string) $payload['statut'] : null,
        );
        $consultation = $this->consultationService->update($id, $input);

        return ['consultation', (string) $consultation->getId(), $this->consultationService->serializeSummary($consultation)];
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function demandeInput(array $payload): UpsertDemandeServiceInput
    {
        $lignes = [];
        foreach ($payload['lignes'] ?? [] as $ligne) {
            if (!is_array($ligne)) {
                continue;
            }
            $lignes[] = new DemandeServiceLigneInput(
                medicamentId: (int) ($ligne['medicamentId'] ?? 0),
                quantite: (int) ($ligne['quantite'] ?? 0),
            );
        }

        return new UpsertDemandeServiceInput(
            serviceId: (int) ($payload['serviceId'] ?? 0),
            motif: isset($payload['motif']) ? (string) $payload['motif'] : null,
            visiteId: isset($payload['visiteId']) ? (int) $payload['visiteId'] : null,
            lignes: $lignes,
        );
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function receptionInput(array $payload): UpsertReceptionInput
    {
        $lignes = [];
        foreach ($payload['lignes'] ?? [] as $ligne) {
            if (!is_array($ligne)) {
                continue;
            }
            $lignes[] = new ReceptionLigneInput(
                medicamentId: (int) ($ligne['medicamentId'] ?? 0),
                numeroLot: (string) ($ligne['numeroLot'] ?? ''),
                datePeremption: (string) ($ligne['datePeremption'] ?? ''),
                quantite: (int) ($ligne['quantite'] ?? 0),
                prixAchatUnitaire: (string) ($ligne['prixAchatUnitaire'] ?? '0'),
                prixVente: isset($ligne['prixVente']) ? (string) $ligne['prixVente'] : null,
            );
        }

        return new UpsertReceptionInput(
            fournisseurId: (int) ($payload['fournisseurId'] ?? 0),
            dateReception: UpsertVenteInput::toDateOnly((string) ($payload['dateReception'] ?? '')) ?? '',
            referenceExterne: $this->strOrNull($payload['referenceExterne'] ?? null),
            lignes: $lignes,
        );
    }

    private function requireServerId(mixed $value, string $label): int
    {
        $id = (int) $value;
        if ($id <= 0) {
            throw new \InvalidArgumentException($label.' : identifiant local non encore synchronisé.');
        }

        return $id;
    }

    private function strOrNull(mixed $value): ?string
    {
        if (null === $value || '' === $value) {
            return null;
        }

        return (string) $value;
    }

    private function positiveIntId(mixed $value): int
    {
        if (is_array($value)) {
            return $this->positiveIntId($value['id'] ?? 0);
        }
        if (is_int($value)) {
            return $value > 0 ? $value : 0;
        }
        if (is_string($value) && ctype_digit(trim($value))) {
            $id = (int) trim($value);

            return $id > 0 ? $id : 0;
        }

        return 0;
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function venteInput(array $payload): UpsertVenteInput
    {
        $lignes = [];
        foreach ($payload['lignes'] ?? [] as $ligne) {
            if (!is_array($ligne)) {
                continue;
            }
            $lotId = $this->positiveIntId($ligne['lotId'] ?? $ligne['lot'] ?? null);
            $medicamentId = $this->positiveIntId($ligne['medicamentId'] ?? $ligne['medicament'] ?? null);
            if ($medicamentId <= 0 && $lotId > 0) {
                $lot = $this->entityManager->find(Lot::class, $lotId);
                $medicamentId = (int) ($lot?->getMedicament()?->getId() ?? 0);
            }
            $lignes[] = new VenteLigneInput(
                medicamentId: $medicamentId,
                lotId: $lotId > 0 ? $lotId : null,
                quantite: (int) ($ligne['quantite'] ?? 0),
                prixUnitaire: $ligne['prixUnitaire'] ?? null,
            );
        }

        return new UpsertVenteInput(
            clientType: (string) ($payload['clientType'] ?? 'PASSANT'),
            patientId: isset($payload['patientId']) ? (string) $payload['patientId'] : null,
            clientNom: isset($payload['clientNom']) ? (string) $payload['clientNom'] : null,
            visiteId: isset($payload['visiteId']) ? (int) $payload['visiteId'] : null,
            modePaiement: (string) ($payload['modePaiement'] ?? 'ESPECES'),
            lignes: $lignes,
            dateVente: $this->syncDateVente($payload),
        );
    }

    /**
     * @param array<string, mixed> $payload
     */
    /**
     * Le .exe envoie dateVente en ISO (T12:00:00 ou …Z). Une vente du jour
     * n'est pas une saisie antérieure : on ne garde que les jours déjà passés à Kinshasa.
     *
     * @param array<string, mixed> $payload
     */
    private function syncDateVente(array $payload): ?string
    {
        $raw = $payload['dateVente'] ?? $payload['optimistic']['dateVente'] ?? null;
        $day = UpsertVenteInput::toDateOnly(null !== $raw ? (string) $raw : null);
        if (null === $day) {
            return null;
        }

        return $day < CalendarDate::today() ? $day : null;
    }

    private function moduleFromAction(string $action): string
    {
        $parts = explode('.', $action);

        return $parts[0] ?? 'sync';
    }

    private function currentPersonnel(): ?Personnel
    {
        $user = $this->security->getUser();

        return $user instanceof Personnel ? $user : null;
    }

    /** @return array<string, mixed> */
    private function serializeStored(SyncMutation $mutation): array
    {
        $result = $mutation->getResult() ?? [];

        return [
            'clientId' => $mutation->getClientId(),
            'status' => $mutation->getStatus(),
            'action' => $mutation->getAction(),
            'entityType' => $mutation->getEntityType(),
            'entityId' => $mutation->getEntityId(),
            'message' => $result['message'] ?? null,
            'data' => SyncMutation::STATUS_ACCEPTED === $mutation->getStatus() ? $result : null,
        ];
    }
}
