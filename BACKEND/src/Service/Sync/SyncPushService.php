<?php

namespace App\Service\Sync;

use App\DTO\Clinique\CreateConsultationInput;
use App\DTO\Clinique\CreateVisiteInput;
use App\DTO\Clinique\UpdateConsultationInput;
use App\DTO\Patient\CreatePatientInput;
use App\DTO\Pharmacie\DemandeServiceLigneInput;
use App\DTO\Pharmacie\UpsertDemandeServiceInput;
use App\DTO\Pharmacie\UpsertVenteInput;
use App\DTO\Pharmacie\VenteLigneInput;
use App\Entity\Personnel;
use App\Entity\SyncMutation;
use App\Exception\ConflictException;
use App\Exception\NotFoundException;
use App\Repository\SyncMutationRepository;
use App\Service\Clinique\ConsultationService;
use App\Service\Clinique\VisiteService;
use App\Service\Patient\PatientService;
use App\Service\Pharmacie\DemandeServiceService;
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
        if (null !== $existing) {
            return $this->serializeStored($existing);
        }

        $record = (new SyncMutation())
            ->setClientId($clientId)
            ->setModule('' !== $module ? $module : $this->moduleFromAction($action))
            ->setAction($action)
            ->setCreatedAt(new \DateTimeImmutable())
            ->setCreatedBy($this->currentPersonnel());

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
            'pharmacie.vente.valider' => $this->venteValider($payload),
            'pharmacie.demande_service.create' => $this->demandeCreate($payload),
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
    private function demandeCreate(array $payload): array
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

        $input = new UpsertDemandeServiceInput(
            serviceId: (int) ($payload['serviceId'] ?? 0),
            motif: isset($payload['motif']) ? (string) $payload['motif'] : null,
            visiteId: isset($payload['visiteId']) ? (int) $payload['visiteId'] : null,
            lignes: $lignes,
        );
        $demande = $this->demandeServiceService->create($input);

        return ['demande_service', (string) $demande->getId(), $this->demandeServiceService->serializeDetail($demande)];
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
    private function venteInput(array $payload): UpsertVenteInput
    {
        $lignes = [];
        foreach ($payload['lignes'] ?? [] as $ligne) {
            if (!is_array($ligne)) {
                continue;
            }
            $lotId = $ligne['lotId'] ?? null;
            $lignes[] = new VenteLigneInput(
                medicamentId: (int) ($ligne['medicamentId'] ?? 0),
                lotId: null !== $lotId && '' !== $lotId ? (int) $lotId : null,
                quantite: (int) ($ligne['quantite'] ?? 0),
            );
        }

        return new UpsertVenteInput(
            clientType: (string) ($payload['clientType'] ?? 'PASSANT'),
            patientId: isset($payload['patientId']) ? (string) $payload['patientId'] : null,
            clientNom: isset($payload['clientNom']) ? (string) $payload['clientNom'] : null,
            visiteId: isset($payload['visiteId']) ? (int) $payload['visiteId'] : null,
            modePaiement: (string) ($payload['modePaiement'] ?? 'ESPECES'),
            lignes: $lignes,
        );
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
