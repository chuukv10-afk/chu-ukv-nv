<?php

namespace App\Service\Pharmacie;

use App\DTO\Pharmacie\CreateAjustementInput;
use App\Entity\MouvementStock;
use App\Exception\ConflictException;
use App\Exception\NotFoundException;
use App\Repository\LotRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Validator\Exception\ValidationFailedException;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final class AjustementService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly LotRepository $lotRepository,
        private readonly StockService $stockService,
        private readonly ValidatorInterface $validator,
    ) {
    }

    /** @return array<string, mixed> */
    public function create(CreateAjustementInput $input): array
    {
        $errors = $this->validator->validate($input);
        if (count($errors) > 0) {
            throw new ValidationFailedException($input, $errors);
        }

        $lot = $this->lotRepository->find($input->lotId);
        if (null === $lot) {
            throw new NotFoundException('Lot non trouvé.');
        }

        $type = strtoupper(trim($input->type));
        $motif = trim($input->motif);
        if (MouvementStock::TYPE_AJUSTEMENT_PLUS === $type) {
            $mouvement = $this->stockService->applyEntree(
                $lot,
                $input->quantite,
                $type,
                MouvementStock::DOC_AJUSTEMENT,
                (int) $lot->getId(),
            );
        } elseif (in_array($type, [
            MouvementStock::TYPE_AJUSTEMENT_MOINS,
            MouvementStock::TYPE_SORTIE_PERTE,
            MouvementStock::TYPE_SORTIE_PEREMPTION,
        ], true)) {
            $mouvement = $this->stockService->applySortie(
                $lot,
                $input->quantite,
                $type,
                MouvementStock::DOC_AJUSTEMENT,
                (int) $lot->getId(),
                false,
            );
        } else {
            throw new ConflictException('Type d\'ajustement invalide.');
        }

        $mouvement->setMotif($motif);
        $this->entityManager->flush();

        $medicament = $lot->getMedicament();

        return [
            'id' => $mouvement->getId(),
            'type' => $mouvement->getType(),
            'sens' => $mouvement->getSens(),
            'quantite' => $mouvement->getQuantite(),
            'motif' => $mouvement->getMotif(),
            'lot' => [
                'id' => $lot->getId(),
                'numeroLot' => $lot->getNumeroLot(),
                'quantiteRestante' => $lot->getQuantiteRestante(),
            ],
            'medicament' => $medicament ? [
                'id' => $medicament->getId(),
                'code' => $medicament->getCode(),
                'libelle' => $medicament->getLibelle(),
            ] : null,
        ];
    }
}
