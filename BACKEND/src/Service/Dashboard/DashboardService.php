<?php

namespace App\Service\Dashboard;

use App\Entity\Consultation;
use App\Entity\DemandeExamen;
use App\Entity\Visite;
use App\Repository\ConsultationRepository;
use App\Repository\DemandeExamenRepository;
use App\Repository\PatientRepository;
use App\Repository\PersonnelRepository;
use App\Repository\ServiceRepository;
use App\Repository\VisiteRepository;

final class DashboardService
{
    private const TIMEZONE = 'Africa/Kinshasa';

    private const WEEK_DAYS = [
        1 => 'Lun',
        2 => 'Mar',
        3 => 'Mer',
        4 => 'Jeu',
        5 => 'Ven',
        6 => 'Sam',
        7 => 'Dim',
    ];

    public function __construct(
        private readonly ServiceRepository $serviceRepository,
        private readonly PatientRepository $patientRepository,
        private readonly PersonnelRepository $personnelRepository,
        private readonly ConsultationRepository $consultationRepository,
        private readonly DemandeExamenRepository $demandeExamenRepository,
        private readonly VisiteRepository $visiteRepository,
    ) {
    }

    /** @return array<string, mixed> */
    public function build(): array
    {
        $tz = new \DateTimeZone(self::TIMEZONE);
        $now = new \DateTimeImmutable('now', $tz);
        $startToday = $now->setTime(0, 0, 0);
        $startTomorrow = $startToday->modify('+1 day');
        $startYesterday = $startToday->modify('-1 day');

        $isoDow = (int) $startToday->format('N');
        $startThisWeek = $startToday->modify('-' . ($isoDow - 1) . ' days');
        $startNextWeek = $startThisWeek->modify('+7 days');
        $startPrevWeek = $startThisWeek->modify('-7 days');

        $startLast7Days = $startToday->modify('-6 days');
        $startPrev7Days = $startLast7Days->modify('-7 days');

        $consultationsToday = $this->consultationRepository->countExcludingStatutBetween(
            Consultation::STATUT_ANNULEE,
            $startToday,
            $startTomorrow,
        );
        $consultationsYesterday = $this->consultationRepository->countExcludingStatutBetween(
            Consultation::STATUT_ANNULEE,
            $startYesterday,
            $startToday,
        );
        $patientsLast7Days = $this->patientRepository->countCreatedBetween($startLast7Days, $startTomorrow);
        $patientsPrev7Days = $this->patientRepository->countCreatedBetween($startPrev7Days, $startLast7Days);

        $byDay = $this->consultationRepository->countExcludingStatutGroupedByDay(
            Consultation::STATUT_ANNULEE,
            $startPrevWeek,
            $startNextWeek,
        );

        return [
            'kpis' => [
                'services' => $this->kpi($this->serviceRepository->count([])),
                'dossiersPatient' => $this->kpi(
                    $this->patientRepository->count([]),
                    $this->percentChange($patientsLast7Days, $patientsPrev7Days),
                    'vs 7 j. préc.',
                ),
                'personnel' => $this->kpi($this->personnelRepository->countActifs()),
                'consultationsJour' => $this->kpi(
                    $consultationsToday,
                    $this->percentChange($consultationsToday, $consultationsYesterday),
                    'vs hier',
                ),
            ],
            'weeklyActivity' => $this->buildWeeklyActivity($startThisWeek, $startPrevWeek, $byDay),
            'moduleDistribution' => $this->buildModuleDistribution($startLast7Days, $startTomorrow),
        ];
    }

    /**
     * @param array<string, int> $byDay
     * @return list<array{day: string, current: int, previous: int}>
     */
    private function buildWeeklyActivity(
        \DateTimeImmutable $startThisWeek,
        \DateTimeImmutable $startPrevWeek,
        array $byDay,
    ): array {
        $rows = [];

        for ($offset = 0; $offset < 7; ++$offset) {
            $currentDate = $startThisWeek->modify('+' . $offset . ' days')->format('Y-m-d');
            $previousDate = $startPrevWeek->modify('+' . $offset . ' days')->format('Y-m-d');
            $isoDow = $offset + 1;

            $rows[] = [
                'day' => self::WEEK_DAYS[$isoDow],
                'current' => $byDay[$currentDate] ?? 0,
                'previous' => $byDay[$previousDate] ?? 0,
            ];
        }

        return $rows;
    }

    /**
     * @return list<array{name: string, value: int, color: string}>
     */
    private function buildModuleDistribution(\DateTimeImmutable $from, \DateTimeImmutable $toExclusive): array
    {
        return [
            [
                'name' => 'Patients',
                'value' => $this->patientRepository->countCreatedBetween($from, $toExclusive),
                'color' => '#6366f1',
            ],
            [
                'name' => 'Consultations',
                'value' => $this->consultationRepository->countExcludingStatutBetween(
                    Consultation::STATUT_ANNULEE,
                    $from,
                    $toExclusive,
                ),
                'color' => '#D81B60',
            ],
            [
                'name' => 'Examens',
                'value' => $this->demandeExamenRepository->countExcludingStatutsBetween(
                    [DemandeExamen::STATUT_ANNULEE, DemandeExamen::STATUT_REFUSEE],
                    $from,
                    $toExclusive,
                ),
                'color' => '#F4C430',
            ],
            [
                'name' => 'Visites',
                'value' => $this->visiteRepository->countExcludingStatutBetween(
                    Visite::STATUT_ANNULEE,
                    $from,
                    $toExclusive,
                ),
                'color' => '#06AED4',
            ],
        ];
    }

    /**
     * @return array{value: int, trend: int|null, trendLabel: string|null}
     */
    private function kpi(int $value, ?int $trend = null, ?string $trendLabel = null): array
    {
        return [
            'value' => $value,
            'trend' => $trend,
            'trendLabel' => null === $trend ? null : $trendLabel,
        ];
    }

    private function percentChange(int $current, int $previous): ?int
    {
        if (0 === $previous && 0 === $current) {
            return null;
        }

        if (0 === $previous) {
            return 100;
        }

        return (int) round((($current - $previous) / $previous) * 100);
    }
}
