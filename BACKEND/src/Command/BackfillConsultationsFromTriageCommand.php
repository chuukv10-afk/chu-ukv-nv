<?php

namespace App\Command;

use App\Service\Clinique\ConsultationService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:consultations:backfill-triage',
    description: 'Crée les consultations manquantes pour les visites triées en CONSULTATION',
)]
final class BackfillConsultationsFromTriageCommand extends Command
{
    public function __construct(
        private readonly ConsultationService $consultationService,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $count = $this->consultationService->backfillMissingFromTriage();

        if (0 === $count) {
            $io->success('Aucune consultation manquante à rattraper.');

            return Command::SUCCESS;
        }

        $io->success(sprintf('%d consultation(s) créée(s) à partir du triage.', $count));

        return Command::SUCCESS;
    }
}
