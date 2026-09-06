<?php

namespace App\Service\Pharmacie;

use App\DTO\Common\PaginatedResult;
use App\DTO\Pharmacie\PharmacieListQuery;
use App\Entity\MouvementStock;
use App\Repository\MouvementStockRepository;
use Symfony\Component\Validator\Exception\ValidationFailedException;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final class MouvementStockService
{
    public function __construct(
        private readonly MouvementStockRepository $mouvementStockRepository,
        private readonly ValidatorInterface $validator,
    ) {
    }

    public function paginate(PharmacieListQuery $query): PaginatedResult
    {
        $errors = $this->validator->validate($query);
        if (count($errors) > 0) {
            throw new ValidationFailedException($query, $errors);
        }

        $result = $this->mouvementStockRepository->paginate(
            $query->page,
            $query->limit,
            $query->search,
            $query->medicamentId,
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

    /** @return array<string, mixed> */
    public function serializeSummary(MouvementStock $mouvement): array
    {
        $lot = $mouvement->getLot();
        $medicament = $lot?->getMedicament();

        return [
            'id' => $mouvement->getId(),
            'type' => $mouvement->getType(),
            'sens' => $mouvement->getSens(),
            'quantite' => $mouvement->getQuantite(),
            'documentType' => $mouvement->getDocumentType(),
            'documentId' => $mouvement->getDocumentId(),
            'motif' => $mouvement->getMotif(),
            'createdAt' => $mouvement->getCreatedAt()?->format(\DateTimeInterface::ATOM),
            'lot' => $lot ? [
                'id' => $lot->getId(),
                'numeroLot' => $lot->getNumeroLot(),
            ] : null,
            'medicament' => $medicament ? [
                'id' => $medicament->getId(),
                'code' => $medicament->getCode(),
                'libelle' => $medicament->getLibelle(),
            ] : null,
        ];
    }
}
