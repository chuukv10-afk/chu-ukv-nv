<?php

namespace App\Service\Pharmacie;

use App\DTO\Common\PaginatedResult;
use App\DTO\Pharmacie\DemandeServiceLigneInput;
use App\DTO\Pharmacie\PharmacieListQuery;
use App\DTO\Pharmacie\RefuserDemandeInput;
use App\DTO\Pharmacie\ReglerDemandeInput;
use App\DTO\Pharmacie\UpsertDemandeServiceInput;
use App\Entity\DemandeService;
use App\Entity\DemandeServiceLigne;
use App\Entity\Medicament;
use App\Entity\MouvementStock;
use App\Entity\Personnel;
use App\Entity\Service;
use App\Entity\Visite;
use App\Exception\ConflictException;
use App\Exception\NotFoundException;
use App\Repository\DemandeServiceRepository;
use App\Repository\MedicamentRepository;
use App\Repository\ServiceRepository;
use App\Repository\VisiteRepository;
use App\Security\Permission\PharmaciePermissions;
use App\Util\CalendarDate;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\Validator\Exception\ValidationFailedException;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final class DemandeServiceService
{
    private const TIMEZONE = CalendarDate::TIMEZONE;

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly DemandeServiceRepository $demandeServiceRepository,
        private readonly ServiceRepository $serviceRepository,
        private readonly VisiteRepository $visiteRepository,
        private readonly MedicamentRepository $medicamentRepository,
        private readonly StockService $stockService,
        private readonly Security $security,
        private readonly ValidatorInterface $validator,
    ) {
    }

    public function paginate(PharmacieListQuery $query): PaginatedResult
    {
        $this->assertValid($query);
        $result = $this->demandeServiceRepository->paginate(
            $query->page,
            $query->limit,
            $query->search,
            $query->statut,
            $query->statutPaiement,
        );

        return new PaginatedResult(
            array_map([$this, 'serializeSummary'], $result['items']),
            $query->page,
            $query->limit,
            $result['total'],
        );
    }

    public function create(UpsertDemandeServiceInput $input): DemandeService
    {
        $this->assertValid($input);
        $dateHistorique = $this->resolveDateHistorique($input);
        $demande = (new DemandeService())
            ->setNumero($this->nextNumero($dateHistorique))
            ->setStatut(DemandeService::STATUT_BROUILLON)
            ->setStatutPaiement(DemandeService::PAIEMENT_SANS_OBJET)
            ->setCreatedAt(new \DateTimeImmutable());
        $this->apply($demande, $input);
        $this->entityManager->persist($demande);
        $this->entityManager->flush();

        return $demande;
    }

    public function createAndDelivrer(UpsertDemandeServiceInput $input): DemandeService
    {
        if (
            !$this->security->isGranted(PharmaciePermissions::DEMANDE_SERVICE_ENVOYER)
            || !$this->security->isGranted(PharmaciePermissions::DEMANDE_SERVICE_DELIVRER)
        ) {
            throw new AccessDeniedHttpException('Permissions requises pour délivrer un approvisionnement de service.');
        }
        if ($this->isSaisieAnterieure($input)) {
            $this->assertCanSaisirAnterieure();
        }

        $connection = $this->entityManager->getConnection();
        $connection->beginTransaction();
        try {
            $demande = $this->create($input);
            $demande = $this->envoyer((int) $demande->getId());
            $demande = $this->delivrer((int) $demande->getId());
            $connection->commit();

            return $demande;
        } catch (\Throwable $exception) {
            if ($connection->isTransactionActive()) {
                $connection->rollBack();
            }
            throw $exception;
        }
    }

    public function update(int $id, UpsertDemandeServiceInput $input): DemandeService
    {
        $this->assertValid($input);
        $demande = $this->requireStatut($id, DemandeService::STATUT_BROUILLON);
        $this->apply($demande, $input);
        $this->entityManager->flush();

        return $demande;
    }

    public function envoyer(int $id): DemandeService
    {
        $demande = $this->requireStatut($id, DemandeService::STATUT_BROUILLON);
        if ($demande->getLignes()->isEmpty()) {
            throw new ConflictException('Impossible d\'envoyer une demande sans ligne.');
        }
        $demande->setStatut(DemandeService::STATUT_ENVOYEE);
        $this->entityManager->flush();

        return $demande;
    }

    public function delivrer(int $id): DemandeService
    {
        $demande = $this->requireStatut($id, DemandeService::STATUT_ENVOYEE);
        $historique = $this->isHistorique($demande);
        $total = 0.0;
        foreach ($demande->getLignes() as $ligne) {
            $medicament = $ligne->getMedicament();
            $lot = $historique
                ? $this->stockService->resolveFefoHistorique($medicament, $ligne->getQuantite())
                : $this->stockService->resolveFefo($medicament, $ligne->getQuantite());
            $ligne->setLot($lot);
            $this->stockService->applySortie(
                $lot,
                $ligne->getQuantite(),
                MouvementStock::TYPE_SORTIE_SERVICE,
                MouvementStock::DOC_DEMANDE_SERVICE,
                (int) $demande->getId(),
                requireVendable: !$historique,
                effectiveAt: $historique ? $this->effectiveAt($demande->getDateLivraison()) : null,
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
        $delivreeAt = $historique
            ? ($demande->getDateLivraison() ?? $this->now())
            : $this->now();
        $demande
            ->setStatut(DemandeService::STATUT_DELIVREE)
            ->setStatutPaiement(DemandeService::PAIEMENT_IMPAYEE)
            ->setDelivreeAt($delivreeAt)
            ->setMontantTotal(number_format($total, 4, '.', ''))
            ->setMontantPaye('0.0000');
        $this->entityManager->flush();

        return $demande;
    }

    public function refuser(int $id, RefuserDemandeInput $input): DemandeService
    {
        $this->assertValid($input);
        $demande = $this->requireStatut($id, DemandeService::STATUT_ENVOYEE);
        $demande
            ->setStatut(DemandeService::STATUT_REFUSEE)
            ->setMotifRefus(trim($input->motif))
            ->setStatutPaiement(DemandeService::PAIEMENT_SANS_OBJET);
        $this->entityManager->flush();

        return $demande;
    }

    public function regler(int $id, ReglerDemandeInput $input): DemandeService
    {
        $this->assertValid($input);
        $demande = $this->getById($id);
        if (!$demande->estCreanceOuverte()) {
            throw new ConflictException('Seule une créance encore due peut être encaissée.');
        }
        $result = DemandePaiementRules::applyPaiement(
            $demande->getMontantTotal(),
            $demande->getMontantPaye(),
            $input->montant,
        );
        $user = $this->security->getUser();
        $demande
            ->setMontantPaye($result['montantPaye'])
            ->setStatutPaiement($result['statutPaiement'])
            ->setModePaiement(strtoupper(trim($input->modePaiement)))
            ->setPayeAt(new \DateTimeImmutable())
            ->setPayePar($user instanceof Personnel ? $user : null);
        $this->entityManager->flush();

        return $demande;
    }

    public function delete(int $id): void
    {
        $demande = $this->requireStatut($id, DemandeService::STATUT_BROUILLON);
        $this->entityManager->remove($demande);
        $this->entityManager->flush();
    }

    public function getById(int $id): DemandeService
    {
        $demande = $this->demandeServiceRepository->find($id);
        if (null === $demande) {
            throw new NotFoundException('Demande de service non trouvée.');
        }

        return $demande;
    }

    /** @return array<string, mixed> */
    public function serializeSummary(DemandeService $demande): array
    {
        $service = $demande->getService();
        $visite = $demande->getVisite();
        $patient = $visite?->getDpi()?->getPatient();

        return [
            'id' => $demande->getId(),
            'numero' => $demande->getNumero(),
            'motif' => $demande->getMotif(),
            'statut' => $demande->getStatut(),
            'statutPaiement' => $demande->getStatutPaiement(),
            'montantTotal' => $demande->getMontantTotal(),
            'montantPaye' => $demande->getMontantPaye(),
            'montantReste' => $demande->getMontantReste(),
            'modePaiement' => $demande->getModePaiement(),
            'motifRefus' => $demande->getMotifRefus(),
            'dateLivraison' => $demande->getDateLivraison()?->format(\DateTimeInterface::ATOM),
            'delivreeAt' => $demande->getDelivreeAt()?->format(\DateTimeInterface::ATOM),
            'historique' => $this->isHistorique($demande),
            'payeAt' => $demande->getPayeAt()?->format(\DateTimeInterface::ATOM),
            'lignesCount' => $demande->getLignes()->count(),
            'serviceId' => $service?->getId(),
            'service' => $service ? [
                'id' => $service->getId(),
                'code' => $service->getCode(),
                'libelle' => $service->getLibelle(),
            ] : null,
            'visiteId' => $visite?->getId(),
            'visite' => $visite ? [
                'id' => $visite->getId(),
                'statut' => $visite->getStatut(),
                'numDossier' => $visite->getDpi()?->getNumDossier(),
                'patientName' => $patient?->getFullName(),
                'patient' => $patient ? [
                    'id' => $patient->getId()?->toRfc4122(),
                    'nom' => $patient->getNom(),
                    'postNom' => $patient->getPostNom(),
                    'prenom' => $patient->getPrenom(),
                ] : null,
            ] : null,
            'createdAt' => $demande->getCreatedAt()?->format(\DateTimeInterface::ATOM),
        ];
    }

    /** @return array<string, mixed> */
    public function serializeDetail(DemandeService $demande): array
    {
        $data = $this->serializeSummary($demande);
        $data['lignes'] = [];
        foreach ($demande->getLignes() as $ligne) {
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
                ] : null,
                'lotId' => $lot?->getId(),
                'lot' => $lot ? [
                    'id' => $lot->getId(),
                    'numeroLot' => $lot->getNumeroLot(),
                ] : null,
                'quantite' => $ligne->getQuantite(),
                'prixUnitaire' => $ligne->getPrixUnitaire(),
                'prixTotal' => $ligne->getPrixTotal(),
            ];
        }

        return $data;
    }

    private function apply(DemandeService $demande, UpsertDemandeServiceInput $input): void
    {
        $service = $this->serviceRepository->find($input->serviceId);
        if (null === $service) {
            throw new NotFoundException('Service non trouvé.');
        }
        $demande
            ->setService($service)
            ->setMotif($this->nullable($input->motif))
            ->setVisite($this->resolveVisiteHospitalisee($input->visiteId, $service));
        $demande->clearLignes();
        $total = 0.0;
        $anterieure = $this->isSaisieAnterieure($input);
        foreach ($this->normalizeLignes($input->lignes) as $ligneInput) {
            $medicament = $this->requireMedicamentActif($ligneInput->medicamentId);
            $prix = $this->resolveLignePrix($medicament, $ligneInput->prixUnitaire, $anterieure);
            $ligneTotal = round((float) $prix * $ligneInput->quantite, 4);
            $total += $ligneTotal;
            $ligne = (new DemandeServiceLigne())
                ->setMedicament($medicament)
                ->setQuantite($ligneInput->quantite)
                ->setPrixUnitaire($prix)
                ->setPrixTotal(number_format($ligneTotal, 4, '.', ''));
            $demande->addLigne($ligne);
        }
        $demande
            ->setMontantTotal(number_format($total, 4, '.', ''))
            ->setDateLivraison($this->resolveDateHistorique($input));
    }

    private function resolveVisiteHospitalisee(?int $visiteId, Service $service): ?Visite
    {
        if (null === $visiteId || $visiteId <= 0) {
            return null;
        }

        $visite = $this->visiteRepository->find($visiteId);
        if (null === $visite) {
            throw new NotFoundException('Visite non trouvée.');
        }
        if (Visite::STATUT_HOSPITALISE !== $visite->getStatut()) {
            throw new ConflictException('La visite doit être hospitalisée.');
        }
        if ($visite->getService()?->getId() !== $service->getId()) {
            throw new ConflictException('Ce patient n\'est pas hospitalisé dans ce service.');
        }

        return $visite;
    }

    private function requireStatut(int $id, string $statut): DemandeService
    {
        $demande = $this->getById($id);
        if ($statut !== $demande->getStatut()) {
            throw new ConflictException('Cette demande n\'est plus au statut attendu.');
        }

        return $demande;
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
        $prefix = 'DSV-' . ($date ?? $this->now())->format('Ymd') . '-';

        return $prefix . str_pad((string) ($this->demandeServiceRepository->countNumeroPrefix($prefix) + 1), 4, '0', STR_PAD_LEFT);
    }

    private function resolveDateHistorique(UpsertDemandeServiceInput $input): ?\DateTimeImmutable
    {
        $day = VenteAnterieureRules::resolveDate(
            $input->dateLivraison,
            $this->security->isGranted(PharmaciePermissions::DEMANDE_SERVICE_SAISIE_ANTERIEURE),
            $this->today()->format('Y-m-d'),
        );

        return null === $day ? null : $this->parseDateLivraison($day);
    }

    private function isSaisieAnterieure(UpsertDemandeServiceInput $input): bool
    {
        return VenteAnterieureRules::isAnterieure($input->dateLivraison, $this->today()->format('Y-m-d'));
    }

    private function isHistorique(DemandeService $demande): bool
    {
        $date = $demande->getDateLivraison() ?? $demande->getDelivreeAt();
        if (!$date instanceof \DateTimeImmutable) {
            return false;
        }

        return $date->format('Y-m-d') < $this->today()->format('Y-m-d');
    }

    private function parseDateLivraison(string $value): \DateTimeImmutable
    {
        $day = CalendarDate::toDateOnly($value);
        $date = \DateTimeImmutable::createFromFormat('Y-m-d', (string) $day, new \DateTimeZone(self::TIMEZONE));
        if (false === $date) {
            throw new ConflictException('Date de livraison invalide.');
        }

        return $date->setTime(12, 0);
    }

    private function resolveLignePrix(Medicament $medicament, mixed $override, bool $autoriserPrixSaisi): string
    {
        return $this->stockService->normalizePrix(
            VenteAnterieureRules::resolvePrixSource($medicament->getPrixVente(), $override, $autoriserPrixSaisi),
        );
    }

    private function assertCanSaisirAnterieure(): void
    {
        if (!$this->security->isGranted(PharmaciePermissions::DEMANDE_SERVICE_SAISIE_ANTERIEURE)) {
            throw new AccessDeniedHttpException('Permission requise pour enregistrer un approvisionnement de service antérieur.');
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
     * @param list<DemandeServiceLigneInput|array<string, mixed>> $lignes
     * @return list<DemandeServiceLigneInput>
     */
    private function normalizeLignes(array $lignes): array
    {
        $normalized = [];
        foreach ($lignes as $ligne) {
            if ($ligne instanceof DemandeServiceLigneInput) {
                $normalized[] = $ligne;
                continue;
            }
            $normalized[] = new DemandeServiceLigneInput(
                (int) ($ligne['medicamentId'] ?? 0),
                (int) ($ligne['quantite'] ?? 0),
                $ligne['prixUnitaire'] ?? null,
            );
        }

        return $normalized;
    }

    private function nullable(?string $value): ?string
    {
        $trimmed = trim((string) $value);

        return '' === $trimmed ? null : $trimmed;
    }

    private function assertValid(object $value): void
    {
        $errors = $this->validator->validate($value);
        if (count($errors) > 0) {
            throw new ValidationFailedException($value, $errors);
        }
    }
}
