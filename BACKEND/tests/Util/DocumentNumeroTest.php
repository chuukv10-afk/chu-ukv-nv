<?php

declare(strict_types=1);

namespace App\Tests\Util;

use App\Util\DocumentNumero;

final class DocumentNumeroTest
{
    public static function run(): array
    {
        $failures = [];

        $first = DocumentNumero::next('VTE-20260829-', []);
        if ('VTE-20260829-0001' !== $first) {
            $failures[] = 'sans existant → 0001, obtenu ' . $first;
        }

        $withGap = DocumentNumero::next('VTE-20260829-', [
            'VTE-20260829-0001',
            'VTE-20260829-0003',
        ]);
        if ('VTE-20260829-0004' !== $withGap) {
            $failures[] = 'trou 0002 → doit prendre MAX+1 = 0004, obtenu ' . $withGap;
        }

        $exeJump = DocumentNumero::next('VTE-20260829-', ['VTE-20260829-0012']);
        if ('VTE-20260829-0013' !== $exeJump) {
            $failures[] = 'sync exe 0012 → 0013, obtenu ' . $exeJump;
        }

        $countWouldCollide = DocumentNumero::next('VTE-20260913-', [
            'VTE-20260913-0001',
            'VTE-20260913-0002',
            'VTE-20260913-0005',
        ]);
        if ('VTE-20260913-0006' !== $countWouldCollide) {
            $failures[] = 'COUNT+1 donnerait 0004 (collision), MAX+1 = 0006, obtenu ' . $countWouldCollide;
        }

        return $failures;
    }
}
