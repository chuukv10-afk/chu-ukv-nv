<?php

declare(strict_types=1);

namespace App\Tests\Pharmacie;

use App\Entity\Lot;
use App\Service\Pharmacie\LotSortieRules;

final class LotSortieRulesTest
{
    public static function run(): array
    {
        $today = new \DateTimeImmutable('2026-09-12');
        $failures = [];

        $ok = self::lot('L-OK', '2026-12-31', 10, Lot::STATUT_DISPONIBLE);
        $perime = self::lot('L-PER', '2026-08-01', 8, Lot::STATUT_PERIME);
        $perimeReste = self::lot('L-PER2', '2026-08-01', 8, Lot::STATUT_DISPONIBLE);
        $bloque = self::lot('L-BLK', '2026-12-31', 10, Lot::STATUT_BLOQUE);
        $epuise = self::lot('L-EPU', '2026-12-31', 0, Lot::STATUT_EPUISE);

        $cases = [
            ['jour : lot vendable qté 3', LotSortieRules::canSortir($ok, 3, $today), true],
            ['jour : qté > reste', LotSortieRules::canSortir($ok, 11, $today), false],
            ['jour : qté 0', LotSortieRules::canSortir($ok, 0, $today), false],
            ['jour : lot périmé', LotSortieRules::canSortir($perime, 2, $today), false],
            ['jour : lot encore DISPONIBLE mais date passée', LotSortieRules::canSortir($perimeReste, 2, $today), false],
            ['jour : lot bloqué', LotSortieRules::canSortir($bloque, 2, $today), false],
            ['jour : lot épuisé', LotSortieRules::canSortir($epuise, 1, $today), false],

            ['historique : lot vendable', LotSortieRules::canSortirHistorique($ok, 3), true],
            ['historique : lot périmé avec reste (rattrapage)', LotSortieRules::canSortirHistorique($perime, 2), true],
            ['historique : lot périmé encore tagué DISPONIBLE', LotSortieRules::canSortirHistorique($perimeReste, 2), true],
            ['historique : lot bloqué interdit', LotSortieRules::canSortirHistorique($bloque, 2), false],
            ['historique : reste insuffisant', LotSortieRules::canSortirHistorique($ok, 11), false],
            ['historique : qté 0', LotSortieRules::canSortirHistorique($ok, 0), false],
            ['historique : épuisé', LotSortieRules::canSortirHistorique($epuise, 1), false],
        ];

        foreach ($cases as [$label, $got, $expected]) {
            if ($got !== $expected) {
                $failures[] = sprintf('%s : attendu %s, obtenu %s', $label, self::b($expected), self::b($got));
            }
        }

        return $failures;
    }

    private static function lot(string $numero, string $peremption, int $reste, string $statut): Lot
    {
        return (new Lot())
            ->setNumeroLot($numero)
            ->setDatePeremption(new \DateTimeImmutable($peremption))
            ->setQuantiteRestante($reste)
            ->setStatut($statut);
    }

    private static function b(bool $value): string
    {
        return $value ? 'true' : 'false';
    }
}
