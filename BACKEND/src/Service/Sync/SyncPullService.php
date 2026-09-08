<?php

namespace App\Service\Sync;

use App\DTO\Common\PaginatedResult;
use App\DTO\Pharmacie\PharmacieListQuery;
use App\DTO\Referentiel\ReferentielListQuery;
use App\Repository\ServiceRepository;
use App\Service\Pharmacie\DemandeServiceService;
use App\Service\Pharmacie\FamilleMedicamentService;
use App\Service\Pharmacie\FournisseurService;
use App\Service\Pharmacie\LotService;
use App\Service\Pharmacie\MedicamentService;
use App\Service\Pharmacie\MouvementStockService;
use App\Service\Pharmacie\ReceptionService;
use App\Service\Pharmacie\UniteMedicamentService;
use App\Service\Pharmacie\VenteService;
use App\Service\Referentiel\PlainteService;
use App\Service\Referentiel\SigneVitalService;

final class SyncPullService
{
    public function __construct(
        private readonly UniteMedicamentService $uniteMedicamentService,
        private readonly FamilleMedicamentService $familleMedicamentService,
        private readonly MedicamentService $medicamentService,
        private readonly FournisseurService $fournisseurService,
        private readonly ReceptionService $receptionService,
        private readonly LotService $lotService,
        private readonly VenteService $venteService,
        private readonly DemandeServiceService $demandeServiceService,
        private readonly MouvementStockService $mouvementStockService,
        private readonly ServiceRepository $serviceRepository,
        private readonly PlainteService $plainteService,
        private readonly SigneVitalService $signeVitalService,
    ) {
    }

    /**
     * @param list<string> $modules
     * @return array<string, mixed>
     */
    public function pull(array $modules): array
    {
        $wanted = array_values(array_filter(array_map(
            static fn (mixed $module): string => strtolower(trim((string) $module)),
            $modules,
        )));
        if ([] === $wanted) {
            $wanted = ['pharmacie', 'organisation', 'referentiel', 'clinique'];
        }

        $data = [
            'pulledAt' => (new \DateTimeImmutable())->format(\DateTimeInterface::ATOM),
            'modules' => [],
        ];

        if (\in_array('pharmacie', $wanted, true)) {
            $data['modules']['pharmacie'] = $this->pullPharmacie();
        }
        if (\in_array('organisation', $wanted, true)) {
            $data['modules']['organisation'] = $this->pullOrganisation();
        }
        if (\in_array('referentiel', $wanted, true)) {
            $data['modules']['referentiel'] = $this->pullReferentiel();
        }
        if (\in_array('clinique', $wanted, true)) {
            $data['modules']['clinique'] = $this->pullClinique();
        }

        return $data;
    }

    /** @return array<string, mixed> */
    private function pullPharmacie(): array
    {
        $medicaments = $this->collectPages(
            fn (int $page): PaginatedResult => $this->medicamentService->paginate(new ReferentielListQuery(page: $page, limit: 100)),
        );
        $stock = [];
        foreach ($medicaments as $medicament) {
            $id = (int) ($medicament['id'] ?? 0);
            if ($id <= 0) {
                continue;
            }
            $stock[] = [
                'medicamentId' => $id,
                'stockDisponible' => $medicament['stockDisponible'] ?? 0,
                'lots' => $this->lotService->vendables($id),
            ];
        }

        $from = (new \DateTimeImmutable('-21 days'))->format('Y-m-d');
        $to = (new \DateTimeImmutable())->format('Y-m-d');

        return [
            'unites' => $this->collectPages(
                fn (int $page): PaginatedResult => $this->uniteMedicamentService->paginate(new ReferentielListQuery(page: $page, limit: 100)),
            ),
            'familles' => $this->collectPages(
                fn (int $page): PaginatedResult => $this->familleMedicamentService->paginate(new ReferentielListQuery(page: $page, limit: 100)),
            ),
            'medicaments' => $medicaments,
            'fournisseurs' => $this->collectPages(
                fn (int $page): PaginatedResult => $this->fournisseurService->paginate(new PharmacieListQuery(page: $page, limit: 100)),
            ),
            'receptions' => $this->collectPages(
                fn (int $page): PaginatedResult => $this->receptionService->paginate(new PharmacieListQuery(page: $page, limit: 100, dateFrom: $from, dateTo: $to)),
            ),
            'ventes' => $this->collectPages(
                fn (int $page): PaginatedResult => $this->venteService->paginate(new PharmacieListQuery(page: $page, limit: 100, dateFrom: $from, dateTo: $to)),
            ),
            'demandes' => $this->collectPages(
                fn (int $page): PaginatedResult => $this->demandeServiceService->paginate(new PharmacieListQuery(page: $page, limit: 100, dateFrom: $from, dateTo: $to)),
            ),
            'lots' => $this->collectPages(
                fn (int $page): PaginatedResult => $this->lotService->paginate(new PharmacieListQuery(page: $page, limit: 100)),
            ),
            'mouvements' => $this->collectPages(
                fn (int $page): PaginatedResult => $this->mouvementStockService->paginate(new PharmacieListQuery(page: $page, limit: 100, dateFrom: $from, dateTo: $to)),
            ),
            'stock' => $stock,
            'alertes' => $this->lotService->alertes(),
        ];
    }

    /**
     * @param callable(int): PaginatedResult $paginate
     * @return list<array<string, mixed>>
     */
    private function collectPages(callable $paginate): array
    {
        $items = [];
        $page = 1;
        do {
            $result = $paginate($page);
            $items = array_merge($items, $result->items);
            $page++;
        } while (count($items) < $result->total && $page <= 40);

        return $items;
    }

    /** @return array<string, mixed> */
    private function pullOrganisation(): array
    {
        $services = [];
        foreach ($this->serviceRepository->findBy([], ['libelle' => 'ASC']) as $service) {
            $services[] = [
                'id' => $service->getId(),
                'code' => $service->getCode(),
                'libelle' => $service->getLibelle(),
            ];
        }

        return ['services' => $services];
    }

    /** @return array<string, mixed> */
    private function pullReferentiel(): array
    {
        return [
            'plaintes' => $this->plainteService->listActifs(),
            'signesVitaux' => $this->signeVitalService->listForTriage(),
        ];
    }

    /** @return array<string, mixed> */
    private function pullClinique(): array
    {
        return [
            'visitesHospitalisees' => $this->venteService->listVisitesHospitalisees(null, null),
        ];
    }
}
