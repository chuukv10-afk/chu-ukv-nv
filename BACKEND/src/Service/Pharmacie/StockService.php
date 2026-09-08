<?php

namespace App\Service\Pharmacie;

use App\Entity\Lot;
use App\Entity\Medicament;
use App\Entity\MouvementStock;
use App\Exception\ConflictException;
use App\Repository\LotRepository;
use Doctrine\ORM\EntityManagerInterface;

final class StockService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly LotRepository $lotRepository,
    ) {
    }

    public function today(): \DateTimeImmutable
    {
        return new \DateTimeImmutable('today');
    }

    public function refreshStatut(Lot $lot): void
    {
        if (Lot::STATUT_BLOQUE === $lot->getStatut()) {
            return;
        }

        if ($lot->getDatePeremption() < $this->today()) {
            $lot->setStatut(Lot::STATUT_PERIME);

            return;
        }

        $lot->setStatut($lot->getQuantiteRestante() > 0 ? Lot::STATUT_DISPONIBLE : Lot::STATUT_EPUISE);
    }

    public function isVendable(Lot $lot): bool
    {
        $this->refreshStatut($lot);

        return Lot::STATUT_DISPONIBLE === $lot->getStatut()
            && $lot->getQuantiteRestante() > 0
            && $lot->getDatePeremption() >= $this->today();
    }

    public function canSortir(Lot $lot, int $quantite): bool
    {
        return $quantite > 0 && $this->isVendable($lot) && $lot->getQuantiteRestante() >= $quantite;
    }

    public function stockDisponible(Medicament $medicament): int
    {
        return $this->lotRepository->stockDisponible($medicament, $this->today());
    }

    /**
     * @return list<Lot>
     */
    public function lotsVendables(Medicament $medicament): array
    {
        return $this->lotRepository->findVendables($medicament, $this->today());
    }

    public function resolveFefo(Medicament $medicament, int $quantite): Lot
    {
        foreach ($this->lotsVendables($medicament) as $lot) {
            if ($lot->getQuantiteRestante() >= $quantite) {
                return $lot;
            }
        }

        throw new ConflictException(sprintf(
            'Stock insuffisant pour « %s » (besoin %d, disponible %d). Une ligne = un lot.',
            $medicament->getLibelle(),
            $quantite,
            $this->stockDisponible($medicament),
        ));
    }

    public function applyEntree(
        Lot $lot,
        int $quantite,
        string $type,
        string $documentType,
        int $documentId,
    ): MouvementStock {
        if ($quantite <= 0) {
            throw new ConflictException('La quantité du mouvement doit être positive.');
        }

        $lot->setQuantiteRestante($lot->getQuantiteRestante() + $quantite);
        $this->refreshStatut($lot);

        return $this->record($lot, $quantite, MouvementStock::SENS_ENTREE, $type, $documentType, $documentId);
    }

    public function applySortie(
        Lot $lot,
        int $quantite,
        string $type,
        string $documentType,
        int $documentId,
        bool $requireVendable = true,
    ): MouvementStock {
        if ($quantite <= 0) {
            throw new ConflictException('La quantité du mouvement doit être positive.');
        }
        if (Lot::STATUT_BLOQUE === $lot->getStatut()) {
            throw new ConflictException(sprintf('Le lot %s est bloqué.', $lot->getNumeroLot()));
        }
        if ($requireVendable && !$this->isVendable($lot)) {
            throw new ConflictException(sprintf(
                'Lot %s insuffisant ou non vendable (reste %d).',
                $lot->getNumeroLot(),
                $lot->getQuantiteRestante(),
            ));
        }
        if ($lot->getQuantiteRestante() < $quantite) {
            throw new ConflictException(sprintf(
                'Lot %s insuffisant (reste %d).',
                $lot->getNumeroLot(),
                $lot->getQuantiteRestante(),
            ));
        }

        $lot->setQuantiteRestante($lot->getQuantiteRestante() - $quantite);
        $this->refreshStatut($lot);

        return $this->record($lot, $quantite, MouvementStock::SENS_SORTIE, $type, $documentType, $documentId);
    }

    private function record(
        Lot $lot,
        int $quantite,
        string $sens,
        string $type,
        string $documentType,
        int $documentId,
    ): MouvementStock {
        $mouvement = (new MouvementStock())
            ->setLot($lot)
            ->setQuantite($quantite)
            ->setSens($sens)
            ->setType($type)
            ->setDocumentType($documentType)
            ->setDocumentId($documentId);

        $this->entityManager->persist($mouvement);

        return $mouvement;
    }

    public function normalizePrix(string $prix): string
    {
        $normalized = str_replace(',', '.', trim($prix));
        if (!is_numeric($normalized) || (float) $normalized < 0) {
            throw new ConflictException('Montant invalide.');
        }

        return number_format((float) $normalized, 4, '.', '');
    }

    public function parseDate(string $value, string $label): \DateTimeImmutable
    {
        $date = \DateTimeImmutable::createFromFormat('Y-m-d', trim($value));
        if (false === $date) {
            throw new ConflictException($label . ' invalide (format AAAA-MM-JJ).');
        }

        return $date->setTime(0, 0);
    }
}
