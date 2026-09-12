<?php

namespace App\Util;

/**
 * Normalise les dates envoyées par le poste bureau (Electron) et le web.
 *
 * Formats observés côté .exe :
 * - date de formulaire          : 2026-08-29
 * - toDateVenteIso()            : 2026-08-29T12:00:00  (sans fuseau)
 * - new Date().toISOString()    : 2026-09-12T12:39:00.000Z
 * - createdAt / hydrate cache   : 2026-09-11T23:30:00.000Z (UTC, jour local Kinshasa différent)
 */
final class CalendarDate
{
    public const TIMEZONE = 'Africa/Kinshasa';

    public static function toDateOnly(mixed $value): ?string
    {
        if ($value instanceof \DateTimeInterface) {
            return self::inKinshasa($value)->format('Y-m-d');
        }
        if (!is_string($value) && !is_int($value) && !is_float($value)) {
            return null;
        }

        $raw = trim((string) $value);
        if ('' === $raw) {
            return null;
        }

        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $raw)) {
            return $raw;
        }

        if (self::hasExplicitTimezone($raw)) {
            try {
                return self::inKinshasa(new \DateTimeImmutable($raw))->format('Y-m-d');
            } catch (\Exception) {
                return self::leadingDate($raw);
            }
        }

        if (preg_match('/^(\d{4}-\d{2}-\d{2})[T ]\d{2}:\d{2}/', $raw, $matches)) {
            return $matches[1];
        }

        try {
            return self::inKinshasa(new \DateTimeImmutable($raw))->format('Y-m-d');
        } catch (\Exception) {
            return self::leadingDate($raw);
        }
    }

    public static function today(): string
    {
        return (new \DateTimeImmutable('today', new \DateTimeZone(self::TIMEZONE)))->format('Y-m-d');
    }

    private static function inKinshasa(\DateTimeInterface $date): \DateTimeImmutable
    {
        $immutable = $date instanceof \DateTimeImmutable
            ? $date
            : \DateTimeImmutable::createFromInterface($date);

        return $immutable->setTimezone(new \DateTimeZone(self::TIMEZONE));
    }

    private static function hasExplicitTimezone(string $raw): bool
    {
        return (bool) preg_match('/(Z|[+-]\d{2}:?\d{2})$/i', $raw);
    }

    private static function leadingDate(string $raw): ?string
    {
        return preg_match('/^(\d{4}-\d{2}-\d{2})/', $raw, $matches) ? $matches[1] : null;
    }
}
