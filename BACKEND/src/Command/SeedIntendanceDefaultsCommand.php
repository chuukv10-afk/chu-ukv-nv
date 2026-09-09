<?php

namespace App\Command;

use App\Service\Intendance\IntendanceSeedService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:intendance:seed-defaults',
    description: 'Précharge les familles et types de bien de l’Intendance',
)]
final class SeedIntendanceDefaultsCommand extends Command
{
    public function __construct(
        private readonly IntendanceSeedService $intendanceSeedService,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $created = $this->intendanceSeedService->seed();

        $io->success(sprintf(
            'Référentiels Intendance : %d famille(s) et %d type(s) créé(s).',
            $created['familles'],
            $created['types'],
        ));

        return Command::SUCCESS;
    }
}
