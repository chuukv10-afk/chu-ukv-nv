<?php

namespace App\Service\Intendance;

use App\Entity\FamilleBien;
use App\Entity\Service;
use App\Repository\BienPatrimonialRepository;

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
    public function proposer(Service $service, FamilleBien $famille, int $count = 1): array
    {
        $count = max(1, min(200, $count));
        $year = (new \DateTimeImmutable())->format('y');
        $prefix = sprintf(
            '%s-%s-%s-%s-',
            self::PREFIX,
            self::serviceSegment($service),
            strtoupper((string) $famille->getCode()),
            $year,
        );

        $next = $this->nextSequence($prefix);
        $codes = [];
        for ($i = 0; $i < $count; ++$i) {
            $codes[] = $prefix . str_pad((string) ($next + $i), 4, '0', STR_PAD_LEFT);
        }

        return $codes;
    }

    private function nextSequence(string $prefix): int
    {
        $max = 1000;
        foreach ($this->bienPatrimonialRepository->findCodesWithPrefix($prefix) as $code) {
            $suffix = substr($code, strlen($prefix));
            if (ctype_digit($suffix)) {
                $max = max($max, (int) $suffix);
            }
        }

        return $max + 1;
    }
}
