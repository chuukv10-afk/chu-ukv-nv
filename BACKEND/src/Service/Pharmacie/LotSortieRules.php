<?php

namespace App\Service\Pharmacie;

use App\Entity\Lot;

/** Règles de sortie : vente du jour (FEFO vendable) vs rattrapage (reste non bloqué). */
final class LotSortieRules
{
    public static function isVendable(Lot $lot, \DateTimeImmutable $today): bool
    {
        if (Lot::STATUT_BLOQUE === $lot->getStatut() || Lot::STATUT_PERIME === $lot->getStatut()) {
            return false;
        }

        return $lot->getQuantiteRestante() > 0
            && $lot->getDatePeremption() >= $today;
    }

    public static function canSortir(Lot $lot, int $quantite, \DateTimeImmutable $today): bool
    {
        return $quantite > 0
            && self::isVendable($lot, $today)
            && $lot->getQuantiteRestante() >= $quantite;
    }

    public static function canSortirHistorique(Lot $lot, int $quantite): bool
    {
        return $quantite > 0
            && Lot::STATUT_BLOQUE !== $lot->getStatut()
            && $lot->getQuantiteRestante() >= $quantite;
    }
}
