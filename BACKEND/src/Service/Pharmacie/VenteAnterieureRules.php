<?php

namespace App\Service\Pharmacie;

use App\Exception\ConflictException;
use App\Util\CalendarDate;

/** Date, permission et prix d'une vente antérieure (sync .exe + API). */
final class VenteAnterieureRules
{
    public const DATE_STOCK_OUVERTURE = '2026-08-28';

    public static function isAnterieure(mixed $dateVente, ?string $today = null): bool
    {
        $day = CalendarDate::toDateOnly($dateVente);
        if (null === $day) {
            return false;
        }

        return $day < ($today ?? CalendarDate::today());
    }

    /**
     * Date à enregistrer : null = vente du jour (l'exe envoie souvent ISO aujourd'hui).
     * Sans permission, un jour passé est ignoré pour ne pas bloquer la synchro.
     */
    public static function resolveDate(mixed $dateVente, bool $hasPermission, ?string $today = null): ?string
    {
        if (!self::isAnterieure($dateVente, $today)) {
            return null;
        }
        if (!$hasPermission) {
            return null;
        }

        $day = CalendarDate::toDateOnly($dateVente);
        if (null === $day) {
            return null;
        }
        if ($day < self::DATE_STOCK_OUVERTURE) {
            throw new ConflictException('La date ne peut pas précéder le stock d\'ouverture du 28/08/2026.');
        }

        return $day;
    }

    public static function resolvePrixSource(mixed $prixCatalogue, mixed $prixSaisi, bool $anterieure): string
    {
        if ($anterieure && null !== $prixSaisi && '' !== $prixSaisi) {
            return (string) $prixSaisi;
        }

        return (string) $prixCatalogue;
    }
}
