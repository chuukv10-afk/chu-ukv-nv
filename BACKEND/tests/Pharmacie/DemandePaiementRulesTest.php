<?php

declare(strict_types=1);

namespace App\Tests\Pharmacie;

use App\Entity\DemandeService;
use App\Exception\ConflictException;
use App\Service\Pharmacie\DemandePaiementRules;

final class DemandePaiementRulesTest
{
    public static function run(): array
    {
        $failures = [];

        $reste = DemandePaiementRules::reste('100.0000', '0.0000');
        if ('100.0000' !== $reste) {
            $failures[] = 'reste plein : attendu 100.0000, obtenu ' . $reste;
        }

        $partiel = DemandePaiementRules::applyPaiement('100.0000', '0.0000', '40');
        if (DemandeService::PAIEMENT_PARTIELLE !== $partiel['statutPaiement'] || '40.0000' !== $partiel['montantPaye']) {
            $failures[] = 'premier acompte : ' . json_encode($partiel);
        }

        $solde = DemandePaiementRules::applyPaiement('100.0000', '40.0000', null);
        if (DemandeService::PAIEMENT_PAYEE !== $solde['statutPaiement'] || '100.0000' !== $solde['montantPaye']) {
            $failures[] = 'solde sans montant : ' . json_encode($solde);
        }

        try {
            DemandePaiementRules::applyPaiement('100.0000', '40.0000', '80');
            $failures[] = 'dépassement : exception attendue';
        } catch (ConflictException) {
        }

        try {
            DemandePaiementRules::applyPaiement('100.0000', '0.0000', '0');
            $failures[] = 'zéro : exception attendue';
        } catch (ConflictException) {
        }

        return $failures;
    }
}
