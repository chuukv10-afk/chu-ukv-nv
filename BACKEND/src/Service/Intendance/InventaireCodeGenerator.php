<?php

namespace App\Service\Intendance;

use App\Entity\Service;
use App\Entity\TypeBien;
use App\Repository\BienPatrimonialRepository;
use App\Util\CalendarDate;

/**
 * Convention :
 * numéro de famille  CHUB-{service}-{année}-{numéro}           ex. CHUB-PHARMO-2026-001 (Étagère)
 * code de l'objet    CHUB-{service}-{année}-{numéro}-{id}      ex. CHUB-PHARMO-2026-001-001
 *
 * Le numéro (001, 002…) identifie le type dans le service pour l'année.
 * L'id identifie chaque objet de ce type.
 */
final class InventaireCodeGenerator
{
    public const PREFIX = 'CHUB';

    public function __construct(
        private readonly BienPatrimonialRepository $bienPatrimonialRepository,
    ) {
    }

    public static function normalize(string $code): string
    {
        $normalized = strtoupper(trim($code));
        $normalized = preg_replace('/[^A-Z0-9._-]/', '', $normalized) ?? '';

        return $normalized;
    }

    public static function isValid(string $code): bool
    {
        $normalized = self::normalize($code);

        return strlen($normalized) >= 5 && strlen($normalized) <= 40;
    }

    public static function serviceSegment(Service $service): string
    {
        $code = strtoupper(trim((string) $service->getCode()));
        $code = preg_replace('/[^A-Z0-9]/', '', $code) ?? '';

        return '' !== $code ? $code : 'SVC';
    }

    /**
     * @return list<string>
     */
    public function proposer(Service $service, TypeBien $type, int $count = 1): array
    {
        $count = max(1, min(200, $count));
        $year = (new \DateTimeImmutable('now', new \DateTimeZone(CalendarDate::TIMEZONE)))->format('Y');
        $stem = sprintf('%s-%s-%s-', self::PREFIX, self::serviceSegment($service), $year);
        [$serie, $nextId] = $this->nextSerieAndId($stem, (int) $type->getId());

        $family = $stem . $this->pad($serie);
        $codes = [];
        for ($i = 0; $i < $count; ++$i) {
            $codes[] = $family . '-' . $this->pad($nextId + $i);
        }

        return $codes;
    }

    /**
     * @return array{0: int, 1: int} numéro de famille, prochain id d'objet
     */
    private function nextSerieAndId(string $stem, int $typeId): array
    {
        $pattern = '/^' . preg_quote($stem, '/') . '(\d+)-(\d+)$/';
        $maxSerie = 0;
        $itemsBySerie = [];

        foreach ($this->bienPatrimonialRepository->findCodeSeriesByPrefix($stem) as $row) {
            if (1 !== preg_match($pattern, $row['code'], $matches)) {
                continue;
            }
            $serie = (int) $matches[1];
            $item = (int) $matches[2];
            $maxSerie = max($maxSerie, $serie);
            if ($row['typeId'] === $typeId) {
                $itemsBySerie[$serie] = max($itemsBySerie[$serie] ?? 0, $item);
            }
        }

        if ([] === $itemsBySerie) {
            return [$maxSerie + 1, 1];
        }

        $serie = min(array_keys($itemsBySerie));

        return [$serie, $itemsBySerie[$serie] + 1];
    }

    private function pad(int $value): string
    {
        return str_pad((string) $value, 3, '0', STR_PAD_LEFT);
    }
}
