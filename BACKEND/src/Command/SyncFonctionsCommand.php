<?php

namespace App\Command;

use App\Service\Referentiel\FonctionService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:referentiel:sync-fonctions',
    description: 'Crée les fonctions RH manquantes (catalogue de l\'état de paie).',
)]
final class SyncFonctionsCommand extends Command
{
    public function __construct(
        private readonly FonctionService $fonctionService,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $result = $this->fonctionService->syncCatalog();

        $io->success(sprintf(
            'Fonctions synchronisées. %d créée(s), %d déjà présente(s), %d service(s) rattaché(s).',
            $result['created'],
            $result['skipped'],
            $result['linked'],
        ));

        return Command::SUCCESS;
    }
}
