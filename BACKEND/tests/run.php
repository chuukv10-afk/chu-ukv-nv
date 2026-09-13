<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use App\Tests\Pharmacie\DemandePaiementRulesTest;
use App\Tests\Pharmacie\LotSortieRulesTest;
use App\Tests\Pharmacie\VenteAnterieureRulesTest;
use App\Tests\Util\CalendarDateTest;

$suites = [
    'dates exe / synchro' => CalendarDateTest::run(),
    'stock FEFO / historique' => LotSortieRulesTest::run(),
    'vente antérieure / permission / prix' => VenteAnterieureRulesTest::run(),
    'encaissement partiel service' => DemandePaiementRulesTest::run(),
];

$failed = 0;
$passed = 0;
foreach ($suites as $name => $failures) {
    if ([] === $failures) {
        fwrite(STDOUT, sprintf("OK   %s\n", $name));
        ++$passed;
        continue;
    }
    ++$failed;
    fwrite(STDERR, sprintf("FAIL %s\n", $name));
    foreach ($failures as $failure) {
        fwrite(STDERR, '     - ' . $failure . "\n");
    }
}

fwrite(STDOUT, sprintf("\n%d suite(s) OK, %d en échec.\n", $passed, $failed));
exit($failed > 0 ? 1 : 0);
