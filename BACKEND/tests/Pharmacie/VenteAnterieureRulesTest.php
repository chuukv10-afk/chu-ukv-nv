<?php

declare(strict_types=1);

namespace App\Tests\Pharmacie;

use App\Exception\ConflictException;
use App\Service\Pharmacie\VenteAnterieureRules;

final class VenteAnterieureRulesTest
{
    public static function run(): array
    {
        $today = '2026-09-12';
        $failures = [];

        $isCases = [
            ['sans date', null, false],
            ['ISO du jour (exe toISOString)', '2026-09-12T12:39:00.000Z', false],
            ['T12:00:00 du jour (exe toDateVenteIso)', '2026-09-12T12:00:00', false],
            ['formulaire hier', '2026-09-11', true],
            ['ISO hier naive', '2026-09-11T12:00:00', true],
            ['UTC 23:30 le 11 → 12 Kinshasa = aujourd’hui', '2026-09-11T23:30:00.000Z', false],
            ['UTC 22:30 le 11 → 11 Kinshasa = hier', '2026-09-11T22:30:00.000Z', true],
            ['stock ouverture', '2026-08-28', true],
        ];

        foreach ($isCases as [$label, $input, $expected]) {
            $got = VenteAnterieureRules::isAnterieure($input, $today);
            if ($got !== $expected) {
                $failures[] = sprintf('isAnterieure %s : attendu %s, obtenu %s', $label, self::b($expected), self::b($got));
            }
        }

        $resolveCases = [
            ['vente du jour sans permission', '2026-09-12T12:00:00', false, null],
            ['vente du jour avec permission ADMIN', '2026-09-12T12:39:00.000Z', true, null],
            ['hier sans permission (sync caisse) → ignoré', '2026-09-11', false, null],
            ['hier avec saisie_anterieure', '2026-09-11T12:00:00', true, '2026-09-11'],
            ['formulaire 29/08 + permission', '2026-08-29', true, '2026-08-29'],
        ];

        foreach ($resolveCases as [$label, $input, $permission, $expected]) {
            try {
                $got = VenteAnterieureRules::resolveDate($input, $permission, $today);
            } catch (ConflictException $exception) {
                $failures[] = sprintf('resolveDate %s : exception inattendue (%s)', $label, $exception->getMessage());
                continue;
            }
            if ($got !== $expected) {
                $failures[] = sprintf('resolveDate %s : attendu %s, obtenu %s', $label, self::show($expected), self::show($got));
            }
        }

        try {
            VenteAnterieureRules::resolveDate('2026-08-27', true, $today);
            $failures[] = 'date avant le 28/08 doit être refusée';
        } catch (ConflictException) {
            // attendu
        }

        $prixCases = [
            ['jour : catalogue gagne', '1500', '900', false, '1500'],
            ['antérieure : prix saisi', '1500', '900', true, '900'],
            ['antérieure : pas de prix → catalogue', '1500', null, true, '1500'],
            ['antérieure : prix vide → catalogue', '1500', '', true, '1500'],
        ];

        foreach ($prixCases as [$label, $catalogue, $saisi, $anterieure, $expected]) {
            $got = VenteAnterieureRules::resolvePrixSource($catalogue, $saisi, $anterieure);
            if ($got !== $expected) {
                $failures[] = sprintf('prix %s : attendu %s, obtenu %s', $label, $expected, $got);
            }
        }

        return $failures;
    }

    private static function b(bool $value): string
    {
        return $value ? 'true' : 'false';
    }

    private static function show(mixed $value): string
    {
        return null === $value ? 'null' : (string) $value;
    }
}
