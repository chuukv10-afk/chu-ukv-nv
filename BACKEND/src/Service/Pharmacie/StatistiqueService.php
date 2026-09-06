<?php

namespace App\Service\Pharmacie;

use App\DTO\Pharmacie\PharmacieListQuery;
use App\Entity\DemandeService;
use App\Entity\MouvementStock;
use App\Entity\Vente;
use App\Repository\DemandeServiceRepository;
use App\Repository\LotRepository;
use App\Repository\MedicamentRepository;
use App\Repository\MouvementStockRepository;
use App\Repository\VenteRepository;
use Symfony\Component\Validator\Exception\ValidationFailedException;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final class StatistiqueService
{
    private const TYPES_CONSOMMATION = [
        MouvementStock::TYPE_SORTIE_VENTE,
        MouvementStock::TYPE_SORTIE_SERVICE,
        MouvementStock::TYPE_SORTIE_HOSPITALISE,
    ];

    public function __construct(
        private readonly MedicamentRepository $medicamentRepository,
        private readonly LotRepository $lotRepository,
        private readonly LotService $lotService,
        private readonly VenteRepository $venteRepository,
        private readonly DemandeServiceRepository $demandeServiceRepository,
        private readonly MouvementStockRepository $mouvementStockRepository,
        private readonly ValidatorInterface $validator,
    ) {
    }

    /** @return array<string, mixed> */
    public function build(PharmacieListQuery $query): array
    {
        $errors = $this->validator->validate($query);
        if (count($errors) > 0) {
            throw new ValidationFailedException($query, $errors);
        }

        $alertes = $this->lotService->alertes();
        $stockBas = $alertes['stockBas'];

        $ventes = $this->filterByCalendarDay(
            $this->venteRepository->findValideesForStats($query->dateFrom, $query->dateTo),
            static fn (Vente $vente): ?\DateTimeInterface => $vente->getDateVente() ?? $vente->getCreatedAt(),
            $query->dateFrom,
            $query->dateTo,
        );

        $demandes = $this->demandeServiceRepository->findDelivreesForStats($query->dateFrom, $query->dateTo);
        $dispensationsService = $this->filterByCalendarDay(
            $demandes,
            static fn (DemandeService $demande): ?\DateTimeInterface => $demande->getDelivreeAt() ?? $demande->getCreatedAt(),
            $query->dateFrom,
            $query->dateTo,
        );
        $servicesPayes = $this->filterByCalendarDay(
            array_values(array_filter(
                $demandes,
                static fn (DemandeService $demande): bool => DemandeService::PAIEMENT_PAYEE === $demande->getStatutPaiement(),
            )),
            static fn (DemandeService $demande): ?\DateTimeInterface => $demande->getPayeAt() ?? $demande->getDelivreeAt(),
            $query->dateFrom,
            $query->dateTo,
        );

        $revenuVentes = 0.0;
        foreach ($ventes as $vente) {
            $revenuVentes += (float) $vente->getMontantTotal();
        }
        $revenuServices = 0.0;
        foreach ($servicesPayes as $demande) {
            $revenuServices += (float) $demande->getMontantTotal();
        }

        return [
            'periode' => [
                'dateFrom' => $query->dateFrom,
                'dateTo' => $query->dateTo,
            ],
            'kpis' => [
                'totalMedicaments' => $this->medicamentRepository->countActifs(),
                'stockValue' => $this->lotRepository->valeurStock(),
                'totalDispensations' => count($ventes) + count($dispensationsService),
                'totalRevenue' => $this->money($revenuVentes + $revenuServices),
                'stockAlerts' => count($stockBas),
            ],
            'salesPerDay' => $this->buildSalesPerDay($ventes, $dispensationsService, $servicesPayes, $query->dateFrom, $query->dateTo),
            'mouvementsPerDay' => $this->buildMouvementsPerDay($query->dateFrom, $query->dateTo),
            'categoryDistribution' => array_map(
                static fn (array $row): array => [
                    'category' => (string) $row['category'],
                    'count' => (int) $row['count'],
                ],
                $this->mouvementStockRepository->sumSortiesByFamille($query->dateFrom, $query->dateTo, self::TYPES_CONSOMMATION),
            ),
            'topMedicaments' => array_map(
                static fn (array $row): array => [
                    'id' => (int) $row['id'],
                    'name' => (string) $row['name'],
                    'count' => (int) $row['count'],
                ],
                $this->mouvementStockRepository->topSortiesByMedicament($query->dateFrom, $query->dateTo, self::TYPES_CONSOMMATION),
            ),
            'stockAlerts' => array_map(
                static fn (array $item): array => [
                    'id' => $item['id'],
                    'name' => $item['libelle'],
                    'code' => $item['code'],
                    'currentStock' => $item['stockDisponible'],
                    'stockMin' => $item['seuilAlerte'],
                ],
                $stockBas,
            ),
        ];
    }

    /**
     * @param list<Vente> $ventes
     * @param list<DemandeService> $dispensationsService
     * @param list<DemandeService> $servicesPayes
     * @return list<array{date: string, count: int, amount: string}>
     */
    private function buildSalesPerDay(
        array $ventes,
        array $dispensationsService,
        array $servicesPayes,
        ?string $dateFrom,
        ?string $dateTo,
    ): array {
        $days = [];
        foreach ($this->eachDay($dateFrom, $dateTo) as $day) {
            $days[$day] = ['count' => 0, 'amount' => 0.0];
        }

        foreach ($ventes as $vente) {
            $day = $this->calendarDay($vente->getDateVente() ?? $vente->getCreatedAt());
            if (null === $day) {
                continue;
            }
            $days[$day] ??= ['count' => 0, 'amount' => 0.0];
            ++$days[$day]['count'];
            $days[$day]['amount'] += (float) $vente->getMontantTotal();
        }

        foreach ($dispensationsService as $demande) {
            $day = $this->calendarDay($demande->getDelivreeAt() ?? $demande->getCreatedAt());
            if (null === $day) {
                continue;
            }
            $days[$day] ??= ['count' => 0, 'amount' => 0.0];
            ++$days[$day]['count'];
        }

        foreach ($servicesPayes as $demande) {
            $day = $this->calendarDay($demande->getPayeAt() ?? $demande->getDelivreeAt());
            if (null === $day) {
                continue;
            }
            $days[$day] ??= ['count' => 0, 'amount' => 0.0];
            $days[$day]['amount'] += (float) $demande->getMontantTotal();
        }

        ksort($days);

        $rows = [];
        foreach ($days as $date => $values) {
            if (0 === $values['count'] && $values['amount'] <= 0) {
                continue;
            }
            $rows[] = [
                'date' => $date,
                'count' => $values['count'],
                'amount' => $this->money($values['amount']),
            ];
        }

        return $rows;
    }

    /**
     * @return list<array{date: string, entrees: int, sorties: int}>
     */
    private function buildMouvementsPerDay(?string $dateFrom, ?string $dateTo): array
    {
        $days = [];
        foreach ($this->mouvementStockRepository->sumQuantiteByDayAndSens($dateFrom, $dateTo, MouvementStock::SENS_ENTREE) as $row) {
            $day = (string) $row['jour'];
            $days[$day] = ['entrees' => (int) $row['qte'], 'sorties' => 0];
        }
        foreach ($this->mouvementStockRepository->sumQuantiteByDayAndSens($dateFrom, $dateTo, MouvementStock::SENS_SORTIE) as $row) {
            $day = (string) $row['jour'];
            $days[$day] ??= ['entrees' => 0, 'sorties' => 0];
            $days[$day]['sorties'] = (int) $row['qte'];
        }

        ksort($days);

        $rows = [];
        foreach ($days as $date => $values) {
            $rows[] = [
                'date' => $date,
                'entrees' => $values['entrees'],
                'sorties' => $values['sorties'],
            ];
        }

        return $rows;
    }

    /**
     * @template T
     * @param list<T> $items
     * @param callable(T): (?\DateTimeInterface) $dateResolver
     * @return list<T>
     */
    private function filterByCalendarDay(array $items, callable $dateResolver, ?string $dateFrom, ?string $dateTo): array
    {
        return array_values(array_filter(
            $items,
            function (mixed $item) use ($dateResolver, $dateFrom, $dateTo): bool {
                return $this->inRange($dateResolver($item), $dateFrom, $dateTo);
            },
        ));
    }

    private function inRange(?\DateTimeInterface $date, ?string $dateFrom, ?string $dateTo): bool
    {
        $day = $this->calendarDay($date);
        if (null === $day) {
            return false;
        }
        if (null !== $dateFrom && '' !== $dateFrom && $day < $dateFrom) {
            return false;
        }
        if (null !== $dateTo && '' !== $dateTo && $day > $dateTo) {
            return false;
        }

        return true;
    }

    private function calendarDay(?\DateTimeInterface $date): ?string
    {
        return $date?->format('Y-m-d');
    }

    /** @return list<string> */
    private function eachDay(?string $dateFrom, ?string $dateTo): array
    {
        if (null === $dateFrom || '' === $dateFrom || null === $dateTo || '' === $dateTo) {
            return [];
        }

        try {
            $start = new \DateTimeImmutable($dateFrom);
            $end = new \DateTimeImmutable($dateTo);
        } catch (\Exception) {
            return [];
        }

        if ($start > $end) {
            return [];
        }

        $days = [];
        $cursor = $start;
        while ($cursor <= $end) {
            $days[] = $cursor->format('Y-m-d');
            $cursor = $cursor->modify('+1 day');
        }

        return $days;
    }

    private function money(float $value): string
    {
        return number_format($value, 4, '.', '');
    }
}
