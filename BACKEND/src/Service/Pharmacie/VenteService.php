<?php

namespace App\Service\Pharmacie;

use App\DTO\Common\PaginatedResult;
use App\DTO\Pharmacie\AnnulerVenteInput;
use App\DTO\Pharmacie\PharmacieListQuery;
use App\DTO\Pharmacie\UpsertVenteInput;
use App\DTO\Pharmacie\VenteLigneInput;
use App\Entity\Medicament;
use App\Entity\MouvementStock;
use App\Entity\Personnel;
use App\Entity\Vente;
use App\Entity\VenteLigne;
use App\Exception\ConflictException;
use App\Exception\NotFoundException;
use App\Repository\LotRepository;
use App\Repository\MedicamentRepository;
use App\Entity\Visite;
use App\Repository\PatientRepository;
use App\Repository\VenteRepository;
use App\Repository\VisiteRepository;
use App\Security\Permission\PharmaciePermissions;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\Validator\Exception\ValidationFailedException;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final class VenteService
{
    private const TIMEZONE = 'Africa/Kinshasa';
    private const DATE_STOCK_OUVERTURE = '2026-08-28';

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly VenteRepository $venteRepository,
        private readonly MedicamentRepository $medicamentRepository,
        private readonly LotRepository $lotRepository,
        private readonly PatientRepository $patientRepository,
        private readonly VisiteRepository $visiteRepository,
        private readonly StockService $stockService,
        private readonly Security $security,
        private readonly ValidatorInterface $validator,
    ) {
    }

    public function paginate(PharmacieListQuery $query): PaginatedResult
    {
        $this->assertValid($query);
        $result = $this->venteRepository->paginate(
            $query->page,
            $query->limit,
            $query->search,
            $query->statut,
            $query->dateFrom,
            $query->dateTo,
        );

        return new PaginatedResult(
            array_map([$this, 'serializeSummary'], $result['items']),
            $query->page,
            $query->limit,
            $result['total'],
        );
    }

    public function create(UpsertVenteInput $input): Vente
    {
        $this->assertValid($input);
        $dateHistorique = $this->resolveDateHistorique($input);
        $vente = (new Vente())
            ->setNumero($this->nextNumero($dateHistorique))
            ->setStatut(Vente::STATUT_BROUILLON)
            ->setCreatedAt(new \DateTimeImmutable());
        $this->apply($vente, $input);
        $this->entityManager->persist($vente);
        $this->entityManager->flush();

        return $vente;
    }

    public function createAndValider(UpsertVenteInput $input): Vente
    {
        $connection = $this->entityManager->getConnection();
        $connection->beginTransaction();
        try {
            $vente = $this->create($input);
            $vente = $this->valider((int) $vente->getId());
            $connection->commit();

            return $vente;
        } catch (\Throwable $exception) {
            if ($connection->isTransactionActive()) {
                $connection->rollBack();
            }
            throw $exception;
        }
    }

    public function update(int $id, UpsertVenteInput $input): Vente
    {
        $this->assertValid($input);
        $vente = $this->requireBrouillon($id);
        $this->apply($vente, $input);
        $this->entityManager->flush();

        return $vente;
    }

    public function valider(int $id): Vente
    {
        $vente = $this->requireBrouillon($id);
        if ($vente->getLignes()->isEmpty()) {
            throw new ConflictException('Impossible de valider une vente sans ligne.');
        }
        $this->assertVisiteHospitalisee($vente);

        $sortieType = Vente::ORIGINE_HOSPITALISE === $vente->getOrigine()
            ? MouvementStock::TYPE_SORTIE_HOSPITALISE
            : MouvementStock::TYPE_SORTIE_VENTE;
        $historique = $this->isHistorique($vente);

        $total = 0.0;
        foreach ($vente->getLignes() as $ligne) {
            $medicament = $ligne->getMedicament();
            $lot = $ligne->getLot();
            if (null !== $lot && $lot->getMedicament()?->getId() !== $medicament?->getId()) {
                $lot = null;
            }
            if ($historique) {
                if (null === $lot || !$this->stockService->canSortirHistorique($lot, $ligne->getQuantite())) {
                    $lot = $this->stockService->resolveFefoHistorique($medicament, $ligne->getQuantite());
                }
            } elseif (null === $lot || !$this->stockService->canSortir($lot, $ligne->getQuantite())) {
                $lot = $this->stockService->resolveFefo($medicament, $ligne->getQuantite());
            }
            $ligne->setLot($lot);

            $this->stockService->applySortie(
                $lot,
                $ligne->getQuantite(),
                $sortieType,
                MouvementStock::DOC_VENTE,
                (int) $vente->getId(),
                requireVendable: !$historique,
                effectiveAt: $historique ? $this->effectiveAt($vente->getDateVente()) : null,
            );

            if ($historique) {
                $prix = $this->stockService->normalizePrix((string) $ligne->getPrixUnitaire());
            } else {
                $prix = $this->stockService->normalizePrix((string) $medicament->getPrixVente());
                $ligne->setPrixUnitaire($prix);
            }
            $ligneTotal = round((float) $prix * $ligne->getQuantite(), 4);
            $ligne->setPrixTotal(number_format($ligneTotal, 4, '.', ''));
            $total += $ligneTotal;
        }

        $dateVente = $historique
            ? ($vente->getDateVente() ?? $this->now())
            : $this->now();

        $vente
            ->setStatut(Vente::STATUT_VALIDEE)
            ->setDateVente($dateVente)
            ->setMontantTotal(number_format($total, 4, '.', ''));
        $this->entityManager->flush();

        return $vente;
    }

    public function annuler(int $id, AnnulerVenteInput $input): Vente
    {
        $this->assertValid($input);
        $vente = $this->getById($id);
        if (Vente::STATUT_VALIDEE !== $vente->getStatut()) {
            throw new ConflictException('Seule une vente validée peut être annulée.');
        }

        $dateVente = $vente->getDateVente() ?? $vente->getCreatedAt() ?? new \DateTimeImmutable();
        $sameDay = $dateVente->format('Y-m-d') === (new \DateTimeImmutable())->format('Y-m-d');
        $required = $sameDay
            ? PharmaciePermissions::VENTE_ANNULER
            : PharmaciePermissions::VENTE_ANNULER_HORS_DELAI;

        if (!$this->security->isGranted($required)) {
            throw new AccessDeniedHttpException($sameDay
                ? 'Permission requise pour annuler une vente le jour même.'
                : 'Permission requise pour annuler une vente après le jour J.');
        }

        $motif = trim((string) $input->motif);
        if (!$sameDay && '' === $motif) {
            throw new ConflictException('Le motif est obligatoire pour une annulation hors délai.');
        }

        foreach ($vente->getLignes() as $ligne) {
            $lot = $ligne->getLot();
            if (null === $lot) {
                continue;
            }
            $this->stockService->applyEntree(
                $lot,
                $ligne->getQuantite(),
                MouvementStock::TYPE_ENTREE_ANNULATION_VENTE,
                MouvementStock::DOC_VENTE,
                (int) $vente->getId(),
            );
        }

        $user = $this->security->getUser();
        $vente
            ->setStatut(Vente::STATUT_ANNULEE)
            ->setAnnuleAt(new \DateTimeImmutable())
            ->setAnnulePar($user instanceof Personnel ? $user : null)
            ->setMotifAnnulation('' === $motif ? null : $motif);
        $this->entityManager->flush();

        return $vente;
    }

    public function delete(int $id): void
    {
        $vente = $this->requireBrouillon($id);
        $this->entityManager->remove($vente);
        $this->entityManager->flush();
    }

    public function getById(int $id): Vente
    {
        $vente = $this->venteRepository->find($id);
        if (null === $vente) {
            throw new NotFoundException('Vente non trouvée.');
        }

        return $vente;
    }

    /** @return array<string, mixed> */
    public function serializeSummary(Vente $vente): array
    {
        $patient = $vente->getPatient();
        $visite = $vente->getVisite();

        return [
            'id' => $vente->getId(),
            'numero' => $vente->getNumero(),
            'dateVente' => $vente->getDateVente()?->format(\DateTimeInterface::ATOM),
            'clientType' => $vente->getClientType(),
            'clientNom' => $vente->getClientNom(),
            'patientId' => $patient?->getId()?->toRfc4122(),
            'patient' => $patient ? [
                'id' => $patient->getId()?->toRfc4122(),
                'nom' => $patient->getNom(),
                'postNom' => $patient->getPostNom(),
                'prenom' => $patient->getPrenom(),
            ] : null,
            'modePaiement' => $vente->getModePaiement(),
            'statut' => $vente->getStatut(),
            'montantTotal' => $vente->getMontantTotal(),
            'lignesCount' => $vente->getLignes()->count(),
            'motifAnnulation' => $vente->getMotifAnnulation(),
            'annuleAt' => $vente->getAnnuleAt()?->format(\DateTimeInterface::ATOM),
            'historique' => $this->isHistorique($vente),
            'origine' => $vente->getOrigine(),
            'visiteId' => $visite?->getId(),
            'visite' => $visite ? [
                'id' => $visite->getId(),
                'statut' => $visite->getStatut(),
                'service' => $visite->getService() ? [
                    'id' => $visite->getService()->getId(),
                    'libelle' => $visite->getService()->getLibelle(),
                ] : null,
            ] : null,
            'createdAt' => $vente->getCreatedAt()?->format(\DateTimeInterface::ATOM),
        ];
    }

    /** @return array<string, mixed> */
    public function serializeDetail(Vente $vente): array
    {
        $data = $this->serializeSummary($vente);
        $data['lignes'] = [];
        foreach ($vente->getLignes() as $ligne) {
            $medicament = $ligne->getMedicament();
            $lot = $ligne->getLot();
            $data['lignes'][] = [
                'id' => $ligne->getId(),
                'medicamentId' => $medicament?->getId(),
                'medicament' => $medicament ? [
                    'id' => $medicament->getId(),
                    'code' => $medicament->getCode(),
                    'libelle' => $medicament->getLibelle(),
                    'prixVente' => $medicament->getPrixVente(),
                    'stockDisponible' => $this->stockService->stockDisponible($medicament),
                ] : null,
                'lotId' => $lot?->getId(),
                'lot' => $lot ? [
                    'id' => $lot->getId(),
                    'numeroLot' => $lot->getNumeroLot(),
                    'datePeremption' => $lot->getDatePeremption()?->format('Y-m-d'),
                    'quantiteRestante' => $lot->getQuantiteRestante(),
                ] : null,
                'quantite' => $ligne->getQuantite(),
                'prixUnitaire' => $ligne->getPrixUnitaire(),
                'prixTotal' => $ligne->getPrixTotal(),
            ];
        }

        return $data;
    }

    private function apply(Vente $vente, UpsertVenteInput $input): void
    {
        $clientType = strtoupper(trim($input->clientType));
        $vente
            ->setClientType($clientType)
            ->setModePaiement(strtoupper(trim($input->modePaiement)))
            ->setPatient(null)
            ->setClientNom(null)
            ->setVisite(null)
            ->setOrigine(Vente::ORIGINE_COMPTOIR);

        if (null !== $input->visiteId && $input->visiteId > 0) {
            $visite = $this->visiteRepository->find($input->visiteId);
            if (null === $visite) {
                throw new NotFoundException('Visite non trouvée.');
            }
            if (Visite::STATUT_HOSPITALISE !== $visite->getStatut()) {
                throw new ConflictException('La visite doit être hospitalisée.');
            }
            $patient = $visite->getDpi()?->getPatient();
            if (null === $patient) {
                throw new ConflictException('Aucun patient rattaché à cette visite.');
            }
            $vente
                ->setClientType(Vente::CLIENT_PATIENT)
                ->setPatient($patient)
                ->setVisite($visite)
                ->setOrigine(Vente::ORIGINE_HOSPITALISE);
        } elseif (Vente::CLIENT_PATIENT === $clientType) {
            $patientId = trim((string) $input->patientId);
            if ('' === $patientId) {
                throw new ConflictException('Le patient est obligatoire pour une vente patient.');
            }
            $patient = $this->patientRepository->find($patientId);
            if (null === $patient) {
                throw new NotFoundException('Patient non trouvé.');
            }
            $vente->setPatient($patient);
        } else {
            $nom = trim((string) $input->clientNom);
            if ('' === $nom) {
                throw new ConflictException('Le nom du passant est obligatoire.');
            }
            $vente->setClientNom($nom);
        }

        $vente->clearLignes();
        $total = 0.0;
        foreach ($this->normalizeLignes($input->lignes) as $ligneInput) {
            $medicament = $this->requireMedicamentActif($ligneInput->medicamentId);
            $lot = null;
            if (null !== $ligneInput->lotId && $ligneInput->lotId > 0) {
                $lot = $this->lotRepository->find($ligneInput->lotId);
                if (null === $lot || $lot->getMedicament()?->getId() !== $medicament->getId()) {
                    $lot = null;
                }
            }

            $prix = $this->resolveLignePrix($medicament, $ligneInput->prixUnitaire, $this->isSaisieAnterieure($input));
            $ligneTotal = round((float) $prix * $ligneInput->quantite, 4);
            $total += $ligneTotal;

            $ligne = (new VenteLigne())
                ->setMedicament($medicament)
                ->setLot($lot)
                ->setQuantite($ligneInput->quantite)
                ->setPrixUnitaire($prix)
                ->setPrixTotal(number_format($ligneTotal, 4, '.', ''));
            $vente->addLigne($ligne);
        }
        $vente->setMontantTotal(number_format($total, 4, '.', ''));
        $vente->setDateVente($this->resolveDateHistorique($input));
    }

    private function assertVisiteHospitalisee(Vente $vente): void
    {
        $visite = $vente->getVisite();
        if (null === $visite) {
            return;
        }
        if (Visite::STATUT_HOSPITALISE !== $visite->getStatut()) {
            throw new ConflictException('La visite n\'est plus hospitalisée. Impossible de valider.');
        }
    }

    /** @return list<array<string, mixed>> */
    public function listVisitesHospitalisees(?string $search, ?int $serviceId = null): array
    {
        $items = [];
        foreach ($this->visiteRepository->findHospitalises($search, 12, $serviceId) as $visite) {
            $patient = $visite->getDpi()?->getPatient();
            $items[] = [
                'id' => $visite->getId(),
                'statut' => $visite->getStatut(),
                'numDossier' => $visite->getDpi()?->getNumDossier(),
                'patientId' => $patient?->getId()?->toRfc4122(),
                'patientName' => $patient?->getFullName(),
                'patient' => $patient ? [
                    'id' => $patient->getId()?->toRfc4122(),
                    'nom' => $patient->getNom(),
                    'postNom' => $patient->getPostNom(),
                    'prenom' => $patient->getPrenom(),
                ] : null,
                'service' => $visite->getService() ? [
                    'id' => $visite->getService()->getId(),
                    'libelle' => $visite->getService()->getLibelle(),
                ] : null,
            ];
        }

        return $items;
    }

    private function requireBrouillon(int $id): Vente
    {
        $vente = $this->getById($id);
        if (Vente::STATUT_BROUILLON !== $vente->getStatut()) {
            throw new ConflictException('Cette vente n\'est plus un brouillon.');
        }

        return $vente;
    }

    private function requireMedicamentActif(int $id): Medicament
    {
        $medicament = $this->medicamentRepository->find($id);
        if (null === $medicament) {
            throw new NotFoundException('Médicament non trouvé.');
        }
        if (Medicament::STATUT_ACTIF !== $medicament->getStatut()) {
            throw new ConflictException('Ce médicament est inactif.');
        }

        return $medicament;
    }

    private function nextNumero(?\DateTimeImmutable $date = null): string
    {
        $prefix = 'VTE-' . ($date ?? $this->now())->format('Ymd') . '-';

        return $prefix . str_pad((string) ($this->venteRepository->countNumeroPrefix($prefix) + 1), 4, '0', STR_PAD_LEFT);
    }

    private function resolveDateHistorique(UpsertVenteInput $input): ?\DateTimeImmutable
    {
        if (!$this->isSaisieAnterieure($input)) {
            return null;
        }

        if (!$this->security->isGranted(PharmaciePermissions::VENTE_SAISIE_ANTERIEURE)) {
            return null;
        }

        $date = $this->parseDateVente((string) $input->dateVente);
        if ($date->format('Y-m-d') < self::DATE_STOCK_OUVERTURE) {
            throw new ConflictException('La date ne peut pas précéder le stock d\'ouverture du 28/08/2026.');
        }

        return $date;
    }

    private function isSaisieAnterieure(UpsertVenteInput $input): bool
    {
        $day = UpsertVenteInput::toDateOnly($input->dateVente);
        if (null === $day) {
            return false;
        }

        return $day < $this->today()->format('Y-m-d');
    }

    private function isHistorique(Vente $vente): bool
    {
        $dateVente = $vente->getDateVente();
        if (!$dateVente instanceof \DateTimeImmutable) {
            return false;
        }

        return $dateVente->format('Y-m-d') < $this->today()->format('Y-m-d');
    }

    private function parseDateVente(string $value): \DateTimeImmutable
    {
        $day = UpsertVenteInput::toDateOnly($value);
        $date = \DateTimeImmutable::createFromFormat('Y-m-d', (string) $day, new \DateTimeZone(self::TIMEZONE));
        if (false === $date) {
            throw new ConflictException('Date de vente invalide.');
        }

        return $date->setTime(12, 0);
    }

    private function resolveLignePrix(Medicament $medicament, mixed $override, bool $autoriserPrixSaisi): string
    {
        if ($autoriserPrixSaisi && null !== $override && '' !== $override) {
            return $this->stockService->normalizePrix((string) $override);
        }

        return $this->stockService->normalizePrix((string) $medicament->getPrixVente());
    }

    private function assertCanSaisirAnterieure(): void
    {
        if (!$this->security->isGranted(PharmaciePermissions::VENTE_SAISIE_ANTERIEURE)) {
            throw new AccessDeniedHttpException('Permission requise pour enregistrer une vente antérieure.');
        }
    }

    private function effectiveAt(?\DateTimeImmutable $date): \DateTimeImmutable
    {
        return $date instanceof \DateTimeImmutable ? $date : $this->now();
    }

    private function now(): \DateTimeImmutable
    {
        return new \DateTimeImmutable('now', new \DateTimeZone(self::TIMEZONE));
    }

    private function today(): \DateTimeImmutable
    {
        return new \DateTimeImmutable('today', new \DateTimeZone(self::TIMEZONE));
    }

    /**
     * @param list<VenteLigneInput|array<string, mixed>> $lignes
     * @return list<VenteLigneInput>
     */
    private function normalizeLignes(array $lignes): array
    {
        $normalized = [];
        foreach ($lignes as $ligne) {
            if ($ligne instanceof VenteLigneInput) {
                $normalized[] = $ligne;
                continue;
            }
            $lotId = $ligne['lotId'] ?? null;
            $normalized[] = new VenteLigneInput(
                (int) ($ligne['medicamentId'] ?? 0),
                null !== $lotId && '' !== $lotId ? (int) $lotId : null,
                (int) ($ligne['quantite'] ?? 0),
                $ligne['prixUnitaire'] ?? null,
            );
        }

        return $normalized;
    }

    private function assertValid(object $value): void
    {
        $errors = $this->validator->validate($value);
        if (count($errors) > 0) {
            throw new ValidationFailedException($value, $errors);
        }
    }
}
