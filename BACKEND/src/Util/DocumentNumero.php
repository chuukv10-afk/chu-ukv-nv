<?php

namespace App\Util;

/** Prochain numéro métier : MAX(suffixe) + 1, pas COUNT (les trous / sync .exe dupliqueraient). */
final class DocumentNumero
{
    /**
     * @param list<string|int|null> $existingNumeros
     */
    public static function next(string $prefix, array $existingNumeros, int $pad = 4): string
    {
        $max = 0;
        $length = strlen($prefix);
        foreach ($existingNumeros as $numero) {
            $suffix = substr((string) $numero, $length);
            if (ctype_digit($suffix)) {
                $max = max($max, (int) $suffix);
            }
        }

        return $prefix . str_pad((string) ($max + 1), $pad, '0', STR_PAD_LEFT);
    }
}
