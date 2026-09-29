<?php

namespace App\Service\Intendance;

use App\Entity\FamilleBien;
use App\Entity\Service;
use App\Entity\TypeBien;
use App\Repository\BienPatrimonialRepository;
use App\Util\CalendarDate;

/**
 * CHUB-{service}-{famille}-{année}-{numéro}-{id}
 * ex. CHUB-PHAR-IT-2026-03001-0001
 *
 * Le numéro (5 chiffres, modifiable) identifie la série.
 * L'id (4 chiffres) identifie chaque objet et reste automatique.
 * Sans équipement sur cette série, l'id part toujours de 0001 (11 exemplaires : 0001 à 0011).
 */
final class InventaireCodeGenerator
{
    public const PREFIX = 'CHUB';

    private const NUMERO_WIDTH = 5;

    private const ID_WIDTH = 4;

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
     * @return array{prefix: string, numero: string, suffixes: list<string>, codes: list<string>}
     */
    public function proposer(Service $service, TypeBien $type, int $count = 1, ?string $numero = null): array
    {
        $count = max(1, min(200, $count));
        $year = (new \DateTimeImmutable('now', new \DateTimeZone(CalendarDate::TIMEZONE)))->format('Y');
        $serviceSeg = self::serviceSegment($service);
        $familleSeg = self::familleSegment($type->getFamille());
        $prefix = sprintf('%s-%s-%s-%s-', self::PREFIX, $serviceSeg, $familleSeg, $year);
        $chosen = $this->normalizeNumero($numero) ?? $this->pad($this->nextNumero($serviceSeg, $year), self::NUMERO_WIDTH);
        $nextId = max(1, $this->nextObjectId($prefix, $chosen));

        $suffixes = [];
        $codes = [];
        for ($i = 0; $i < $count; ++$i) {
            $suffix = $this->pad($nextId + $i, self::ID_WIDTH);
            $suffixes[] = $suffix;
            $codes[] = $prefix . $chosen . '-' . $suffix;
        }

        return [
            'prefix' => $prefix,
            'numero' => $chosen,
            'suffixes' => $suffixes,
            'codes' => $codes,
        ];
    }

    public static function familleSegment(?FamilleBien $famille): string
    {
        $code = strtoupper(trim((string) $famille?->getCode()));
        $code = preg_replace('/[^A-Z0-9]/', '', $code) ?? '';

        return '' !== $code ? $code : 'XX';
    }

    private function normalizeNumero(?string $numero): ?string
    {
        $digits = preg_replace('/\D/', '', (string) $numero) ?? '';
        if ('' === $digits) {
            return null;
        }

        return $this->pad((int) $digits, self::NUMERO_WIDTH);
    }

    private function nextNumero(string $serviceSeg, string $year): int
    {
        $prefix = self::PREFIX . '-' . $serviceSeg . '-';
        $pattern = '/^' . preg_quote($prefix, '/') . '[A-Z0-9]+-' . preg_quote($year, '/') . '-(\d+)-\d+$/';
        $max = 0;
        foreach ($this->bienPatrimonialRepository->findCodeSeriesByPrefix($prefix) as $row) {
            if (1 === preg_match($pattern, $row['code'], $matches)) {
                $max = max($max, (int) $matches[1]);
            }
        }

        return $max + 1;
    }

    /**
     * Prochain id d'objet pour une série précise (préfixe + numéro).
     * Aucun code sur cette série → 1, donc 0001.
     */
    private function nextObjectId(string $prefix, string $numero): int
    {
        $stem = $prefix . $numero . '-';
        $codes = array_map(
            static fn (array $row): string => (string) ($row['code'] ?? ''),
            $this->bienPatrimonialRepository->findCodeSeriesByPrefix($stem),
        );

        return self::nextIdFromCodes($codes, $stem);
    }

    /**
     * @param list<string> $codes
     */
    public static function nextIdFromCodes(array $codes, string $stem): int
    {
        $pattern = '/^' . preg_quote($stem, '/') . '(\d{4})$/';
        $max = 0;
        foreach ($codes as $code) {
            if (1 === preg_match($pattern, $code, $matches)) {
                $max = max($max, (int) $matches[1]);
            }
        }

        return $max + 1;
    }

    public static function numeroFromCode(string $code): ?string
    {
        $normalized = self::normalize($code);
        if (1 !== preg_match('/^CHUB-[A-Z0-9]+-[A-Z0-9]+-\d{4}-(\d+)-\d+$/', $normalized, $matches)) {
            return null;
        }

        return str_pad((string) max(0, (int) $matches[1]), self::NUMERO_WIDTH, '0', STR_PAD_LEFT);
    }

    private function pad(int $value, int $width): string
    {
        return str_pad((string) max(0, $value), $width, '0', STR_PAD_LEFT);
    }
}
