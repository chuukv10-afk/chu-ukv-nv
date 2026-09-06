<?php

namespace App\Service\Sync;

use App\Repository\ServiceRepository;
use App\Repository\VisiteRepository;
use App\Service\Pharmacie\FamilleMedicamentService;
use App\Service\Pharmacie\LotService;
use App\Service\Pharmacie\MedicamentService;
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
        private readonly LotService $lotService,
        private readonly VenteService $venteService,
        private readonly ServiceRepository $serviceRepository,
        private readonly VisiteRepository $visiteRepository,
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
        $medicaments = $this->medicamentService->listActifs();
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

        return [
            'unites' => $this->uniteMedicamentService->listActifs(),
            'familles' => $this->familleMedicamentService->listActifs(),
            'medicaments' => $medicaments,
            'stock' => $stock,
            'alertes' => $this->lotService->alertes(),
        ];
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
