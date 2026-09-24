<?php

namespace App\Service\Facturation;

use App\Entity\Facture;
use App\Exception\ConflictException;

final class RemiseCalculator
{
    public static function normalizeType(?string $type): string
    {
        $normalized = strtoupper(trim((string) $type));
        if ('' === $normalized) {
            return Facture::REMISE_NONE;
        }

        if (!in_array($normalized, Facture::REMISE_TYPES, true)) {
            throw new ConflictException('Type de remise invalide.');
        }

        return $normalized;
    }

    public static function compute(string $brut, ?string $type, ?string $valeur): array
    {
        $normalizedType = self::normalizeType($type);
        $base = self::money($brut);
        $amount = self::toFloat($valeur);

        if (Facture::REMISE_NONE === $normalizedType || $amount <= 0.0) {
            return [
                'type' => Facture::REMISE_NONE,
                'valeur' => '0.00',
                'montant' => '0.00',
            ];
        }

        if (Facture::REMISE_POURCENTAGE === $normalizedType) {
            if ($amount > 100.0) {
                throw new ConflictException('Une remise en pourcentage ne peut pas dépasser 100 %.');
            }
            $remise = (float) $base * $amount / 100.0;
        } else {
            $remise = $amount;
        }

        if ($remise > (float) $base + 0.0001) {
            throw new ConflictException('La remise ne peut pas dépasser le montant concerné.');
        }

        return [
            'type' => $normalizedType,
            'valeur' => self::money((string) $amount),
            'montant' => self::money((string) $remise),
        ];
    }

    public static function isActive(?string $type, ?string $valeur): bool
    {
        $normalizedType = self::normalizeType($type);

        return Facture::REMISE_NONE !== $normalizedType && self::toFloat($valeur) > 0.0;
    }

    public static function money(string $value): string
    {
        return number_format(self::toFloat($value), 2, '.', '');
    }

    public static function toFloat(?string $value): float
    {
        $normalized = str_replace([' ', ','], ['', '.'], trim((string) $value));
        if ('' === $normalized || !is_numeric($normalized)) {
            return 0.0;
        }

        return max(0.0, (float) $normalized);
    }
}
