<?php

namespace App\Entity;

/**
 * Catégories de la grille CHHU (A0, A1, A, B, C).
 * Les montants sont importés ; cette classe ne calcule aucun indice.
 */
final class CategorieTarifaire
{
    public const A0 = 'A0';
    public const A1 = 'A1';
    public const A = 'A';
    public const B = 'B';
    public const C = 'C';

    /**
     * @return list<string>
     */
    public static function codes(): array
    {
        return [self::A0, self::A1, self::A, self::B, self::C];
    }

    public static function normalize(string $code): string
    {
        return strtoupper(trim($code));
    }

    public static function isValid(?string $code): bool
    {
        if (null === $code || '' === trim($code)) {
            return false;
        }

        return in_array(self::normalize($code), self::codes(), true);
    }

    public static function requiresStructure(string $code): bool
    {
        $normalized = self::normalize($code);

        return self::A1 === $normalized || self::C === $normalized;
    }

    /**
     * @return list<string>
     */
    public static function allowedStructureTypes(string $code): array
    {
        return match (self::normalize($code)) {
            self::A1 => [Structure::TYPE_MUTUELLE],
            self::C => [
                Structure::TYPE_ASSURANCE,
                Structure::TYPE_ONG,
                Structure::TYPE_ENTREPRISE,
                Structure::TYPE_PARTENAIRE,
            ],
            default => [],
        };
    }

    /**
     * @return list<array{code: string, libelle: string, requiresStructure: bool}>
     */
    public static function definitions(): array
    {
        return [
            ['code' => self::A0, 'libelle' => 'A0 — Indigent', 'requiresStructure' => false],
            ['code' => self::A1, 'libelle' => 'A1 — Mutuelle locale', 'requiresStructure' => true],
            ['code' => self::A, 'libelle' => 'A — Tarif standard', 'requiresStructure' => false],
            ['code' => self::B, 'libelle' => 'B — Privé', 'requiresStructure' => false],
            ['code' => self::C, 'libelle' => 'C — Sponsorisé / assurance', 'requiresStructure' => true],
        ];
    }
}
