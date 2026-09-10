<?php

namespace App\Service\Clinique;

use App\Entity\CertificatAptitude;

final class AptitudeCalculator
{
    /**
     * Saisie en mètres (ex. 1.75). Une valeur > 3 est interprétée comme des cm.
     */
    public static function tailleMetres(?float $taille): ?float
    {
        if (null === $taille || $taille <= 0) {
            return null;
        }

        return $taille > 3 ? round($taille / 100, 2) : $taille;
    }

    public static function tailleCentimetres(?float $taille): ?float
    {
        $metres = self::tailleMetres($taille);

        return null === $metres ? null : round($metres * 100, 2);
    }

    public static function imc(?float $poidsKg, ?float $taille): ?float
    {
        $metres = self::tailleMetres($taille);
        if (null === $poidsKg || null === $metres || $poidsKg <= 0) {
            return null;
        }

        return round($poidsKg / ($metres * $metres), 2);
    }

    /**
     * Classification OMS adulte (18–65 ans).
     */
    public static function imcClasse(?float $imc): ?string
    {
        if (null === $imc) {
            return null;
        }

        if ($imc < 18.5) {
            return CertificatAptitude::IMC_MAIGREUR;
        }
        if ($imc < 25) {
            return CertificatAptitude::IMC_NORMAL;
        }
        if ($imc < 30) {
            return CertificatAptitude::IMC_SURPOIDS;
        }
        if ($imc < 35) {
            return CertificatAptitude::IMC_OBESITE_I;
        }
        if ($imc < 40) {
            return CertificatAptitude::IMC_OBESITE_II;
        }

        return CertificatAptitude::IMC_OBESITE_III;
    }

    /** Indice de Pignet = T_cm − (P + C). */
    public static function pignet(?float $taille, ?float $poidsKg, ?float $perimetreThoraciqueCm): ?float
    {
        $tailleCm = self::tailleCentimetres($taille);
        if (null === $tailleCm || null === $poidsKg || null === $perimetreThoraciqueCm) {
            return null;
        }

        return round($tailleCm - ($poidsKg + $perimetreThoraciqueCm), 2);
    }

    public static function pignetRobustesse(?float $indice): ?string
    {
        if (null === $indice) {
            return null;
        }

        if ($indice <= 10) {
            return CertificatAptitude::PIGNET_TRES_FORTE;
        }
        if ($indice <= 15) {
            return CertificatAptitude::PIGNET_FORTE;
        }
        if ($indice <= 20) {
            return CertificatAptitude::PIGNET_BONNE;
        }
        if ($indice <= 25) {
            return CertificatAptitude::PIGNET_MOYENNE;
        }
        if ($indice <= 30) {
            return CertificatAptitude::PIGNET_FAIBLE;
        }
        if ($indice <= 35) {
            return CertificatAptitude::PIGNET_TRES_FAIBLE;
        }

        return CertificatAptitude::PIGNET_EXTREME;
    }

    /** Indice de Ruffier = ((P1 + P2 + P3) − 200) / 10. */
    public static function ruffier(?int $p1, ?int $p2, ?int $p3): ?float
    {
        if (null === $p1 || null === $p2 || null === $p3) {
            return null;
        }

        return round(($p1 + $p2 + $p3 - 200) / 10, 2);
    }

    public static function ruffierClasse(?float $indice): ?string
    {
        if (null === $indice) {
            return null;
        }

        if ($indice < 0) {
            return CertificatAptitude::RUFFIER_EXCELLENTE;
        }
        if ($indice <= 5) {
            return CertificatAptitude::RUFFIER_BONNE;
        }
        if ($indice <= 10) {
            return CertificatAptitude::RUFFIER_MOYENNE;
        }
        if ($indice <= 15) {
            return CertificatAptitude::RUFFIER_INSUFFISANTE;
        }

        return CertificatAptitude::RUFFIER_MAUVAISE;
    }

    /** Indice de Dickson = ((P2 − 70) + 2 × (P3 − P1)) / 10. */
    public static function dickson(?int $p1, ?int $p2, ?int $p3): ?float
    {
        if (null === $p1 || null === $p2 || null === $p3) {
            return null;
        }

        return round(($p2 - 70 + 2 * ($p3 - $p1)) / 10, 2);
    }

    public static function dicksonClasse(?float $indice): ?string
    {
        if (null === $indice) {
            return null;
        }

        if ($indice < 0) {
            return CertificatAptitude::DICKSON_EXCELLENT;
        }
        if ($indice <= 2) {
            return CertificatAptitude::DICKSON_TRES_BON;
        }
        if ($indice <= 4) {
            return CertificatAptitude::DICKSON_BON;
        }
        if ($indice <= 6) {
            return CertificatAptitude::DICKSON_MOYEN;
        }
        if ($indice <= 8) {
            return CertificatAptitude::DICKSON_FAIBLE;
        }

        return CertificatAptitude::DICKSON_MAUVAIS;
    }

    public static function proposeVerdict(?string $ruffierClasse): ?string
    {
        if (null === $ruffierClasse) {
            return null;
        }

        return in_array($ruffierClasse, [
            CertificatAptitude::RUFFIER_INSUFFISANTE,
            CertificatAptitude::RUFFIER_MAUVAISE,
        ], true)
            ? CertificatAptitude::VERDICT_INAPTE
            : CertificatAptitude::VERDICT_APTE;
    }

    /** @return array<string, mixed> */
    public static function compute(
        ?float $poidsKg,
        ?float $taille,
        ?float $perimetreThoraciqueCm,
        ?int $p1,
        ?int $p2,
        ?int $p3,
    ): array {
        $imc = self::imc($poidsKg, $taille);
        $pignet = self::pignet($taille, $poidsKg, $perimetreThoraciqueCm);
        $ruffier = self::ruffier($p1, $p2, $p3);
        $dickson = self::dickson($p1, $p2, $p3);
        $ruffierClasse = self::ruffierClasse($ruffier);

        return [
            'tailleM' => self::tailleMetres($taille),
            'imc' => $imc,
            'imcClasse' => self::imcClasse($imc),
            'pignet' => $pignet,
            'pignetRobustesse' => self::pignetRobustesse($pignet),
            'ruffier' => $ruffier,
            'dickson' => $dickson,
            'ruffierClasse' => $ruffierClasse,
            'dicksonClasse' => self::dicksonClasse($dickson),
            'verdictPropose' => self::proposeVerdict($ruffierClasse),
        ];
    }
}
