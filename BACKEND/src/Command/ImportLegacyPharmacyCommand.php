<?php

namespace App\Command;

use App\Service\Pharmacie\ImportLegacyPharmacyService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

#[AsCommand(
    name: 'app:pharmacie:import-legacy-sigai',
    description: 'Importe le catalogue et le stock d’ouverture depuis un dump SQL SIGAI (pharmacie).',
)]
final class ImportLegacyPharmacyCommand extends Command
{
    public function __construct(
        private readonly ImportLegacyPharmacyService $importLegacyPharmacyService,
        #[Autowire('%kernel.project_dir%')]
        private readonly string $projectDir,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('file', null, InputOption::VALUE_REQUIRED, 'Chemin vers export_tables_*.sql')
            ->addOption('apply', null, InputOption::VALUE_NONE, 'Écrit en base (sinon simulation)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $file = (string) ($input->getOption('file') ?: $this->defaultDumpPath());
        $apply = (bool) $input->getOption('apply');

        if (!is_readable($file)) {
            $io->error(sprintf('Fichier introuvable : %s', $file));

            return Command::FAILURE;
        }

        $sql = file_get_contents($file);
        if (false === $sql || '' === trim($sql)) {
            $io->error('Dump SQL vide.');

            return Command::FAILURE;
        }

        $io->title($apply ? 'Import SIGAI (écriture)' : 'Import SIGAI (simulation)');
        $io->text($file);

        try {
            $result = $apply
                ? $this->importLegacyPharmacyService->import($sql)
                : $this->importLegacyPharmacyService->preview($sql);
        } catch (\Throwable $exception) {
            $io->error($exception->getMessage());

            return Command::FAILURE;
        }

        $io->table(
            ['Élément', 'Créés / à créer'],
            [
                ['Unités', (string) $result['unites']],
                ['Familles', (string) $result['familles']],
                ['Fournisseurs', (string) $result['fournisseurs']],
                ['Médicaments', (string) $result['medicaments']],
                ['Lots vendables (1er lot INIT-*)', (string) $result['lotsVendables']],
                ['Lots déjà périmés', (string) $result['lotsPerimes']],
                ['Fiches sans stock', (string) $result['sansStock']],
                ['Lots déjà présents (ignorés)', (string) $result['skippedLots']],
            ],
        );

        if ($result['warnings'] !== []) {
            $io->warning(sprintf('%d avertissement(s).', count($result['warnings'])));
            $io->listing(array_slice($result['warnings'], 0, 30));
            if (count($result['warnings']) > 30) {
                $io->text(sprintf('… %d de plus', count($result['warnings']) - 30));
            }
        }

        if (!$apply) {
            $io->note('Aucune écriture. Relancer avec --apply pour enregistrer le stock d’ouverture.');
        } else {
            $io->success('Reprise enregistrée : fournisseur REPRISE, réceptions validées, 1 lot par médicament en stock.');
        }

        return Command::SUCCESS;
    }

    private function defaultDumpPath(): string
    {
        return dirname($this->projectDir, 2) . DIRECTORY_SEPARATOR . 'export_tables_2026-09-09-01-50-37.sql';
    }
}
