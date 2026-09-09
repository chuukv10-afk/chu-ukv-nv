<?php

namespace App\Service\Pharmacie;

use App\DTO\Common\PaginatedResult;
use App\DTO\Pharmacie\PharmacieListQuery;
use App\DTO\Pharmacie\UpdateLotInput;
use App\Entity\Lot;
use App\Exception\ConflictException;
use App\Exception\NotFoundException;
use App\Repository\LotRepository;
use App\Repository\MedicamentRepository;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Validator\Exception\ValidationFailedException;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final class LotService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly LotRepository $lotRepository,
        private readonly MedicamentRepository $medicamentRepository,
        private readonly StockService $stockService,
        private readonly ValidatorInterface $validator,
    ) {
    }

    public function paginate(PharmacieListQuery $query): PaginatedResult
    {
        $errors = $this->validator->validate($query);
        if (count($errors) > 0) {
            throw new ValidationFailedException($query, $errors);
        }

        $result = $this->lotRepository->paginate($query->page, $query->limit, $query->search, $query->statut, $query->medicamentId);

        return new PaginatedResult(
            array_map([$this, 'serializeSummary'], $result['items']),
            $query->page,
            $query->limit,
            $result['total'],
        );
    }

    /** @return array<string, mixed> */
    public function alertes(): array
    {
        $today = $this->stockService->today();
        $horizon = $today->modify('+90 days');
        $perimes = [];
        $proche = [];

        foreach ($this->lotRepository->findPerimesOuProches($today, $horizon) as $lot) {
            $this->stockService->refreshStatut($lot);
            $item = $this->serializeSummary($lot);
            if ($lot->getDatePeremption() < $today) {
                $perimes[] = $item;
            } else {
                $proche[] = $item;
            }
        }

        $stockBas = [];
        foreach ($this->medicamentRepository->findActifs() as $medicament) {
            $stock = $this->stockService->stockDisponible($medicament);
            if ($stock <= $medicament->getSeuilAlerte()) {
                $stockBas[] = [
                    'id' => $medicament->getId(),
                    'code' => $medicament->getCode(),
                    'libelle' => $medicament->getLibelle(),
                    'seuilAlerte' => $medicament->getSeuilAlerte(),
                    'stockDisponible' => $stock,
                ];
            }
        }

        return [
            'perimes' => $perimes,
            'peremptionProche' => $proche,
            'stockBas' => $stockBas,
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function vendables(int $medicamentId): array
    {
        $medicament = $this->medicamentRepository->find($medicamentId);
        if (null === $medicament) {
            return [];
        }

        return array_map([$this, 'serializeSummary'], $this->stockService->lotsVendables($medicament));
    }

    public function update(int $id, UpdateLotInput $input): Lot
    {
        $errors = $this->validator->validate($input);
        if (count($errors) > 0) {
            throw new ValidationFailedException($input, $errors);
        }

        $lot = $this->lotRepository->find($id);
        if (null === $lot) {
            throw new NotFoundException('Lot introuvable.');
        }

        $numeroLot = strtoupper(trim($input->numeroLot));
        if ('' === $numeroLot) {
            throw new ConflictException('Le numéro de lot est obligatoire.');
        }

        $datePeremption = $this->stockService->parseDate($input->datePeremption, 'Date de péremption');
        $medicament = $lot->getMedicament();
        if (null === $medicament) {
            throw new ConflictException('Lot sans médicament.');
        }

        $existing = $this->lotRepository->findOneByMedicamentAndNumero($medicament, $numeroLot);
        if (null !== $existing && $existing->getId() !== $lot->getId()) {
            throw new ConflictException('Ce numéro de lot existe déjà pour ce médicament.');
        }

        $lot->setNumeroLot($numeroLot);
        $lot->setDatePeremption($datePeremption);
        $this->stockService->refreshStatut($lot);

        try {
            $this->entityManager->flush();
        } catch (UniqueConstraintViolationException) {
            throw new ConflictException('Ce numéro de lot existe déjà pour ce médicament.');
        }

        return $lot;
    }

    /** @return array<string, mixed> */
    public function serializeSummary(Lot $lot): array
    {
        $this->stockService->refreshStatut($lot);
        $medicament = $lot->getMedicament();

        return [
            'id' => $lot->getId(),
            'numeroLot' => $lot->getNumeroLot(),
            'datePeremption' => $lot->getDatePeremption()?->format('Y-m-d'),
            'quantiteRestante' => $lot->getQuantiteRestante(),
            'prixAchatUnitaire' => $lot->getPrixAchatUnitaire(),
            'statut' => $lot->getStatut(),
            'medicamentId' => $medicament?->getId(),
            'medicament' => $medicament ? [
                'id' => $medicament->getId(),
                'code' => $medicament->getCode(),
                'libelle' => $medicament->getLibelle(),
            ] : null,
        ];
    }
}
