<?php

namespace App\Service\Pharmacie;

use App\Entity\DemandeService;
use App\Exception\ConflictException;

final class DemandePaiementRules
{
    public static function money(float $value): string
    {
        return number_format(round($value, 4), 4, '.', '');
    }

    public static function reste(string $total, string $paye): string
    {
        return self::money(max(0, (float) $total - (float) $paye));
    }

    /**
     * @return array{montantPaye: string, statutPaiement: string, encaisse: string}
     */
    public static function applyPaiement(string $total, string $dejaPaye, mixed $montantSaisi): array
    {
        $reste = (float) self::reste($total, $dejaPaye);
        $raw = null === $montantSaisi ? '' : trim((string) $montantSaisi);
        $paye = '' === $raw ? $reste : round((float) str_replace(',', '.', $raw), 4);

        if ($paye <= 0) {
            throw new ConflictException('Le montant encaissé doit être supérieur à 0.');
        }
        if ($paye > $reste + 0.00005) {
            throw new ConflictException('Le montant dépasse le reste dû.');
        }

        $nouveau = (float) $dejaPaye + $paye;
        $solde = $nouveau + 0.00005 >= (float) $total;

        return [
            'montantPaye' => $solde ? self::money((float) $total) : self::money($nouveau),
            'statutPaiement' => $solde ? DemandeService::PAIEMENT_PAYEE : DemandeService::PAIEMENT_PARTIELLE,
            'encaisse' => self::money($paye),
        ];
    }
}
