<?php

namespace App\Command;

use App\Service\Facturation\GrilleTarifaireImportService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

#[AsCommand(
    name: 'app:facturation:import-grille',
    description: 'Importe la grille tarifaire CHHU consolidée (5 colonnes CDF, sans calcul d’indice).',
)]
final class ImportGrilleTarifaireCommand extends Command
{
    public function __construct(
        private readonly GrilleTarifaireImportService $grilleTarifaireImportService,
        #[Autowire('%kernel.project_dir%/data/grille-tarifaire-chhu.xlsx')]
        private readonly string $defaultImportFile,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('file', null, InputOption::VALUE_REQUIRED, 'Chemin vers la grille consolidée .xlsx');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $file = (string) ($input->getOption('file') ?: $this->defaultImportFile);

        $io->text(sprintf('Import depuis : %s', $file));

        try {
            $result = $this->grilleTarifaireImportService->importFromFile($file);
        } catch (\Throwable $exception) {
            $io->error($exception->getMessage());

            return Command::FAILURE;
        }

        $io->success(sprintf(
            'Grille importée : %d créé(s), %d mis à jour, %d ignoré(s).',
            $result['imported'],
            $result['updated'],
            $result['skipped'],
        ));

        return Command::SUCCESS;
    }
}
