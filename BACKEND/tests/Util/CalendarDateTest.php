<?php

declare(strict_types=1);

namespace App\Tests\Util;

use App\Util\CalendarDate;

/**
 * Rejoue les formats réellement envoyés par le .exe + les bords UTC/Kinshasa.
 *
 * Kinshasa = UTC+1 toute l'année (pas d'heure d'été).
 */
final class CalendarDateTest
{
    public static function cases(): array
    {
        $today = CalendarDate::today();
        $yesterday = (new \DateTimeImmutable($today, new \DateTimeZone(CalendarDate::TIMEZONE)))
            ->modify('-1 day')
            ->format('Y-m-d');
        $tomorrow = (new \DateTimeImmutable($today, new \DateTimeZone(CalendarDate::TIMEZONE)))
            ->modify('+1 day')
            ->format('Y-m-d');

        return [
            'vide null' => [null, null, null],
            'vide string' => ['', null, null],
            'espaces' => ['   ', null, null],
            'invalide' => ['pas-une-date', null, null],

            'formulaire antérieur' => ['2026-08-29', '2026-08-29', '2026-08-29'],
            'stock ouverture' => ['2026-08-28', '2026-08-28', '2026-08-28'],
            'formulaire hier' => [$yesterday, $yesterday, $yesterday],
            'formulaire aujourd’hui' => [$today, $today, null],
            'formulaire demain' => [$tomorrow, $tomorrow, null],

            'exe toDateVenteIso antérieur' => ['2026-08-29T12:00:00', '2026-08-29', '2026-08-29'],
            'exe toDateVenteIso hier' => [$yesterday . 'T12:00:00', $yesterday, $yesterday],
            'exe toDateVenteIso aujourd’hui' => [$today . 'T12:00:00', $today, null],
            'exe espace au lieu de T' => ['2026-08-29 12:00:00', '2026-08-29', '2026-08-29'],

            'exe toISOString Z midi UTC → 13h Kinshasa même jour' => [
                '2026-08-29T12:00:00.000Z',
                '2026-08-29',
                '2026-08-29',
            ],
            'exe UTC 23:30 veille → 00:30 Kinshasa lendemain' => [
                '2026-09-11T23:30:00.000Z',
                '2026-09-12',
                '2026-09-12' < $today ? '2026-09-12' : null,
            ],
            'exe UTC 22:30 → 23:30 Kinshasa même jour' => [
                '2026-09-11T22:30:00.000Z',
                '2026-09-11',
                '2026-09-11' < $today ? '2026-09-11' : null,
            ],
            'offset Kinshasa +01:00' => ['2026-08-29T00:15:00+01:00', '2026-08-29', '2026-08-29'],
            'offset +0100 compact' => ['2026-08-29T00:15:00+0100', '2026-08-29', '2026-08-29'],

            'optimistic cache ISO naive minuit' => ['2026-08-29T00:00:00', '2026-08-29', '2026-08-29'],
            'millisecondes sans Z' => ['2026-08-29T12:39:15.123', '2026-08-29', '2026-08-29'],
        ];
    }

    public static function run(): array
    {
        $failures = [];
        foreach (self::cases() as $label => [$input, $expectedDay, $expectedSync]) {
            $gotDay = CalendarDate::toDateOnly($input);
            $gotSync = CalendarDate::forSyncVente($input);
            if ($gotDay !== $expectedDay) {
                $failures[] = sprintf(
                    '%s [toDateOnly] attendu %s, obtenu %s (entrée %s)',
                    $label,
                    self::show($expectedDay),
                    self::show($gotDay),
                    self::show($input),
                );
            }
            if ($gotSync !== $expectedSync) {
                $failures[] = sprintf(
                    '%s [forSyncVente] attendu %s, obtenu %s (entrée %s, today=%s)',
                    $label,
                    self::show($expectedSync),
                    self::show($gotSync),
                    self::show($input),
                    CalendarDate::today(),
                );
            }
            if (null !== $gotDay && 10 !== strlen($gotDay)) {
                $failures[] = sprintf('%s [toDateOnly] doit faire 10 caractères, obtenu %s', $label, self::show($gotDay));
            }
            if (null !== $gotSync && 10 !== strlen($gotSync)) {
                $failures[] = sprintf('%s [forSyncVente] doit faire 10 caractères, obtenu %s', $label, self::show($gotSync));
            }
        }

        $object = new \DateTimeImmutable('2026-09-11 23:30:00', new \DateTimeZone('UTC'));
        $fromObject = CalendarDate::toDateOnly($object);
        if ('2026-09-12' !== $fromObject) {
            $failures[] = 'DateTimeInterface UTC 23:30 → Kinshasa 12/09, obtenu ' . self::show($fromObject);
        }

        $payloadLive = CalendarDate::forSyncVente((new \DateTimeImmutable('now', new \DateTimeZone('UTC')))->format('Y-m-d\TH:i:s.v\Z'));
        if (null !== $payloadLive && $payloadLive >= CalendarDate::today()) {
            $failures[] = 'toISOString() du jour ne doit pas rester une vente antérieure';
        }

        return $failures;
    }

    private static function show(mixed $value): string
    {
        if (null === $value) {
            return 'null';
        }

        return (string) $value;
    }
}
