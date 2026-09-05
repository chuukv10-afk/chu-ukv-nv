<?php

namespace App\Command;

use App\Service\Referentiel\Cim10ImportService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

#[AsCommand(
    name: 'app:referentiel:import-cim10',
    description: 'Importe le référentiel CIM-10 FR dans la table maladie',
)]
final class ImportCim10Command extends Command
{
    public function __construct(
        private readonly Cim10ImportService $cim10ImportService,
        #[Autowire('%kernel.project_dir%/assets/referentiel/cim10_import.jsonl.gz')]
        private readonly string $defaultImportFile,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('file', null, InputOption::VALUE_REQUIRED, 'Chemin vers le fichier .jsonl ou .jsonl.gz')
            ->addOption('truncate', null, InputOption::VALUE_NONE, 'Vide la table maladie avant import');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $file = (string) ($input->getOption('file') ?: $this->defaultImportFile);
        $truncate = (bool) $input->getOption('truncate');

        if ($truncate) {
            $io->warning('La table maladie sera vidée avant import.');
        }

        $io->text(sprintf('Import depuis : %s', $file));

        try {
            $result = $this->cim10ImportService->importFromFile($file, $truncate);
        } catch (\Throwable $exception) {
            $io->error($exception->getMessage());

            return Command::FAILURE;
        }

        $io->success(sprintf(
            'Import CIM-10 terminé : %d entrée(s) importée(s), %d ignorée(s)%s.',
            $result['imported'],
            $result['skipped'],
            $result['truncated'] ? ', table vidée au préalable' : '',
        ));

        return Command::SUCCESS;
    }
}
