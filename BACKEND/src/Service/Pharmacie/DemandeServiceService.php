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
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Validator\Exception\ValidationFailedException;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final class DemandeServiceService
{
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
        $demande = (new DemandeService())
            ->setNumero($this->nextNumero())
            ->setStatut(DemandeService::STATUT_BROUILLON)
            ->setStatutPaiement(DemandeService::PAIEMENT_SANS_OBJET)
            ->setCreatedAt(new \DateTimeImmutable());
        $this->apply($demande, $input);
        $this->entityManager->persist($demande);
        $this->entityManager->flush();

        return $demande;
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
        $total = 0.0;
        foreach ($demande->getLignes() as $ligne) {
            $medicament = $ligne->getMedicament();
            $lot = $this->stockService->resolveFefo($medicament, $ligne->getQuantite());
            $ligne->setLot($lot);
            $this->stockService->applySortie(
                $lot,
                $ligne->getQuantite(),
                MouvementStock::TYPE_SORTIE_SERVICE,
                MouvementStock::DOC_DEMANDE_SERVICE,
                (int) $demande->getId(),
            );
            $prix = $this->stockService->normalizePrix((string) $medicament->getPrixVente());
            $ligneTotal = round((float) $prix * $ligne->getQuantite(), 4);
            $ligne->setPrixUnitaire($prix)->setPrixTotal(number_format($ligneTotal, 4, '.', ''));
            $total += $ligneTotal;
        }
        $demande
            ->setStatut(DemandeService::STATUT_DELIVREE)
            ->setStatutPaiement(DemandeService::PAIEMENT_IMPAYEE)
            ->setDelivreeAt(new \DateTimeImmutable())
            ->setMontantTotal(number_format($total, 4, '.', ''));
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
        if (DemandeService::STATUT_DELIVREE !== $demande->getStatut()
            || DemandeService::PAIEMENT_IMPAYEE !== $demande->getStatutPaiement()) {
            throw new ConflictException('Seule une créance impayée peut être réglée.');
        }
        $user = $this->security->getUser();
        $demande
            ->setStatutPaiement(DemandeService::PAIEMENT_PAYEE)
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
            'modePaiement' => $demande->getModePaiement(),
            'motifRefus' => $demande->getMotifRefus(),
            'delivreeAt' => $demande->getDelivreeAt()?->format(\DateTimeInterface::ATOM),
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
        foreach ($this->normalizeLignes($input->lignes) as $ligneInput) {
            $medicament = $this->requireMedicamentActif($ligneInput->medicamentId);
            $prix = $this->stockService->normalizePrix((string) $medicament->getPrixVente());
            $ligneTotal = round((float) $prix * $ligneInput->quantite, 4);
            $total += $ligneTotal;
            $ligne = (new DemandeServiceLigne())
                ->setMedicament($medicament)
                ->setQuantite($ligneInput->quantite)
                ->setPrixUnitaire($prix)
                ->setPrixTotal(number_format($ligneTotal, 4, '.', ''));
            $demande->addLigne($ligne);
        }
        $demande->setMontantTotal(number_format($total, 4, '.', ''));
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

    private function nextNumero(): string
    {
        $prefix = 'DSV-' . (new \DateTimeImmutable())->format('Ymd') . '-';

        return $prefix . str_pad((string) ($this->demandeServiceRepository->countNumeroPrefix($prefix) + 1), 4, '0', STR_PAD_LEFT);
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
