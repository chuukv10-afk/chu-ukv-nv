<?php

namespace App\Service\Pharmacie;

use App\DTO\Common\PaginatedResult;
use App\DTO\Pharmacie\PharmacieListQuery;
use App\DTO\Pharmacie\ReceptionLigneInput;
use App\DTO\Pharmacie\UpsertReceptionInput;
use App\Entity\Fournisseur;
use App\Entity\Lot;
use App\Entity\Medicament;
use App\Entity\MouvementStock;
use App\Entity\Reception;
use App\Entity\ReceptionLigne;
use App\Exception\ConflictException;
use App\Exception\NotFoundException;
use App\Repository\FournisseurRepository;
use App\Repository\LotRepository;
use App\Repository\MedicamentRepository;
use App\Repository\ReceptionRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Validator\Exception\ValidationFailedException;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final class ReceptionService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly ReceptionRepository $receptionRepository,
        private readonly FournisseurRepository $fournisseurRepository,
        private readonly MedicamentRepository $medicamentRepository,
        private readonly LotRepository $lotRepository,
        private readonly StockService $stockService,
        private readonly ValidatorInterface $validator,
    ) {
    }

    public function paginate(PharmacieListQuery $query): PaginatedResult
    {
        $this->assertValid($query);
        $result = $this->receptionRepository->paginate($query->page, $query->limit, $query->search, $query->statut);

        return new PaginatedResult(
            array_map([$this, 'serializeSummary'], $result['items']),
            $query->page,
            $query->limit,
            $result['total'],
        );
    }

    public function create(UpsertReceptionInput $input): Reception
    {
        $this->assertValid($input);
        $reception = (new Reception())
            ->setNumero($this->nextNumero())
            ->setStatut(Reception::STATUT_BROUILLON)
            ->setCreatedAt(new \DateTimeImmutable());
        $this->apply($reception, $input);
        $this->entityManager->persist($reception);
        $this->entityManager->flush();

        return $reception;
    }

    public function update(int $id, UpsertReceptionInput $input): Reception
    {
        $this->assertValid($input);
        $reception = $this->requireBrouillon($id);
        $this->apply($reception, $input);
        $this->entityManager->flush();

        return $reception;
    }

    public function valider(int $id): Reception
    {
        $reception = $this->requireBrouillon($id);
        if ($reception->getLignes()->isEmpty()) {
            throw new ConflictException('Impossible de valider une réception sans ligne.');
        }

        foreach ($reception->getLignes() as $ligne) {
            $this->entrerLigne($reception, $ligne);
        }

        $reception->setStatut(Reception::STATUT_VALIDEE);
        $this->entityManager->flush();

        return $reception;
    }

    public function delete(int $id): void
    {
        $reception = $this->requireBrouillon($id);
        $this->entityManager->remove($reception);
        $this->entityManager->flush();
    }

    public function getById(int $id): Reception
    {
        $reception = $this->receptionRepository->find($id);
        if (null === $reception) {
            throw new NotFoundException('Réception non trouvée.');
        }

        return $reception;
    }

    /** @return array<string, mixed> */
    public function serializeSummary(Reception $reception): array
    {
        $fournisseur = $reception->getFournisseur();

        return [
            'id' => $reception->getId(),
            'numero' => $reception->getNumero(),
            'dateReception' => $reception->getDateReception()?->format('Y-m-d'),
            'referenceExterne' => $reception->getReferenceExterne(),
            'statut' => $reception->getStatut(),
            'lignesCount' => $reception->getLignes()->count(),
            'fournisseurId' => $fournisseur?->getId(),
            'fournisseur' => $fournisseur ? [
                'id' => $fournisseur->getId(),
                'code' => $fournisseur->getCode(),
                'libelle' => $fournisseur->getLibelle(),
            ] : null,
            'createdAt' => $reception->getCreatedAt()?->format(\DateTimeInterface::ATOM),
        ];
    }

    /** @return array<string, mixed> */
    public function serializeDetail(Reception $reception): array
    {
        $data = $this->serializeSummary($reception);
        $data['lignes'] = [];
        foreach ($reception->getLignes() as $ligne) {
            $medicament = $ligne->getMedicament();
            $data['lignes'][] = [
                'id' => $ligne->getId(),
                'medicamentId' => $medicament?->getId(),
                'medicament' => $medicament ? [
                    'id' => $medicament->getId(),
                    'code' => $medicament->getCode(),
                    'libelle' => $medicament->getLibelle(),
                    'dci' => $medicament->getDci(),
                    'forme' => $medicament->getForme(),
                    'dosage' => $medicament->getDosage(),
                    'prixVente' => $medicament->getPrixVente(),
                ] : null,
                'numeroLot' => $ligne->getNumeroLot(),
                'datePeremption' => $ligne->getDatePeremption()?->format('Y-m-d'),
                'quantite' => $ligne->getQuantite(),
                'prixAchatUnitaire' => $ligne->getPrixAchatUnitaire(),
                'lotId' => $medicament
                    ? $this->lotRepository->findOneByMedicamentAndNumero($medicament, (string) $ligne->getNumeroLot())?->getId()
                    : null,
            ];
        }

        return $data;
    }

    private function apply(Reception $reception, UpsertReceptionInput $input): void
    {
        $fournisseur = $this->fournisseurRepository->find($input->fournisseurId);
        if (null === $fournisseur) {
            throw new NotFoundException('Fournisseur non trouvé.');
        }
        if (Fournisseur::STATUT_ACTIF !== $fournisseur->getStatut()) {
            throw new ConflictException('Ce fournisseur est inactif.');
        }

        $dateReception = $this->stockService->parseDate($input->dateReception, 'Date de réception');
        $reception
            ->setFournisseur($fournisseur)
            ->setDateReception($dateReception)
            ->setReferenceExterne($this->nullable($input->referenceExterne));

        $reception->clearLignes();
        foreach ($this->normalizeLignes($input->lignes) as $ligneInput) {
            $medicament = $this->requireMedicamentActif($ligneInput->medicamentId);
            $datePeremption = $this->stockService->parseDate($ligneInput->datePeremption, 'Date de péremption');
            if ($datePeremption <= $dateReception) {
                throw new ConflictException('La péremption doit être postérieure à la date de réception.');
            }

            $this->applyPrixVenteCatalogue($medicament, $ligneInput->prixVente);

            $ligne = (new ReceptionLigne())
                ->setMedicament($medicament)
                ->setNumeroLot(strtoupper(trim($ligneInput->numeroLot)))
                ->setDatePeremption($datePeremption)
                ->setQuantite($ligneInput->quantite)
                ->setPrixAchatUnitaire($this->stockService->normalizePrix($ligneInput->prixAchatUnitaire));
            $reception->addLigne($ligne);
        }
    }

    private function entrerLigne(Reception $reception, ReceptionLigne $ligne): void
    {
        $medicament = $ligne->getMedicament();
        $numeroLot = (string) $ligne->getNumeroLot();
        $prix = (string) $ligne->getPrixAchatUnitaire();
        $lot = $this->lotRepository->findOneByMedicamentAndNumero($medicament, $numeroLot);

        if (null !== $lot) {
            if ($this->stockService->normalizePrix((string) $lot->getPrixAchatUnitaire()) !== $prix) {
                throw new ConflictException(sprintf(
                    'Le lot %s existe déjà avec un autre prix d\'achat. Utilisez un n° de lot distinct.',
                    $numeroLot,
                ));
            }
        } else {
            $lot = (new Lot())
                ->setMedicament($medicament)
                ->setNumeroLot($numeroLot)
                ->setDatePeremption($ligne->getDatePeremption())
                ->setPrixAchatUnitaire($prix)
                ->setQuantiteRestante(0)
                ->setReceptionLigne($ligne)
                ->setStatut(Lot::STATUT_DISPONIBLE);
            $this->entityManager->persist($lot);
        }

        $this->stockService->applyEntree(
            $lot,
            $ligne->getQuantite(),
            MouvementStock::TYPE_ENTREE_RECEPTION,
            MouvementStock::DOC_RECEPTION,
            (int) $reception->getId(),
        );
    }

    private function applyPrixVenteCatalogue(Medicament $medicament, ?string $prixVente): void
    {
        $trimmed = trim((string) $prixVente);
        if ('' === $trimmed) {
            return;
        }

        $medicament->setPrixVente($this->stockService->normalizePrix($trimmed));
    }

    private function requireBrouillon(int $id): Reception
    {
        $reception = $this->getById($id);
        if (Reception::STATUT_BROUILLON !== $reception->getStatut()) {
            throw new ConflictException('Cette réception est déjà validée.');
        }

        return $reception;
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
        $prefix = 'REC-' . (new \DateTimeImmutable())->format('Ymd') . '-';

        return $prefix . str_pad((string) ($this->receptionRepository->countNumeroPrefix($prefix) + 1), 4, '0', STR_PAD_LEFT);
    }

    /**
     * @param list<ReceptionLigneInput|array<string, mixed>> $lignes
     * @return list<ReceptionLigneInput>
     */
    private function normalizeLignes(array $lignes): array
    {
        $normalized = [];
        foreach ($lignes as $ligne) {
            if ($ligne instanceof ReceptionLigneInput) {
                $normalized[] = $ligne;
                continue;
            }
            $prixVente = $ligne['prixVente'] ?? null;
            $normalized[] = new ReceptionLigneInput(
                (int) ($ligne['medicamentId'] ?? 0),
                (string) ($ligne['numeroLot'] ?? ''),
                (string) ($ligne['datePeremption'] ?? ''),
                (int) ($ligne['quantite'] ?? 0),
                (string) ($ligne['prixAchatUnitaire'] ?? '0'),
                null !== $prixVente && '' !== $prixVente ? (string) $prixVente : null,
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
