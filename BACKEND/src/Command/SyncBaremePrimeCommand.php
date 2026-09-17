<?php

namespace App\Command;

use App\Service\Rh\BaremePrimeService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:rh:sync-bareme-prime',
    description: 'Charge le barème de prime locale (grade + fonction) déduit de l’état Excel.',
)]
final class SyncBaremePrimeCommand extends Command
{
    public function __construct(
        private readonly BaremePrimeService $baremePrimeService,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $result = $this->baremePrimeService->syncCatalog();

        $io->success(sprintf(
            'Barème synchronisé. %d créé(s), %d mis à jour, %d ignoré(s) (fonction absente).',
            $result['created'],
            $result['updated'],
            $result['skipped'],
        ));

        return Command::SUCCESS;
    }
}
