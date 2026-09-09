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
        $requested = $input->getOption('file');
        $file = is_string($requested) && '' !== trim($requested)
            ? trim($requested)
            : ($this->findDumpPath() ?? $this->expectedDumpPath());
        $apply = (bool) $input->getOption('apply');

        if (!is_readable($file)) {
            $io->error(sprintf('Fichier introuvable : %s', $file));
            $io->writeln('Le dump SQL n’est pas sur le serveur. Copiez-le, puis :');
            $io->writeln('  php bin/console app:pharmacie:import-legacy-sigai --file=/chemin/vers/export_tables_2026-09-09-01-50-37.sql');
            $io->writeln('Puis, après simulation OK :');
            $io->writeln('  php bin/console app:pharmacie:import-legacy-sigai --file=/chemin/vers/export_tables_2026-09-09-01-50-37.sql --apply');

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

    private function findDumpPath(): ?string
    {
        $filename = 'export_tables_2026-09-09-01-50-37.sql';
        $dirs = array_unique(array_filter([
            getcwd() ?: null,
            $this->projectDir,
            dirname($this->projectDir),
            dirname($this->projectDir, 2),
        ]));

        foreach ($dirs as $dir) {
            $path = $dir . DIRECTORY_SEPARATOR . $filename;
            if (is_readable($path)) {
                return $path;
            }
        }

        foreach ($dirs as $dir) {
            $matches = glob($dir . DIRECTORY_SEPARATOR . 'export_tables_*.sql') ?: [];
            if ($matches !== []) {
                return $matches[0];
            }
        }

        return null;
    }

    private function expectedDumpPath(): string
    {
        return dirname($this->projectDir, 2) . DIRECTORY_SEPARATOR . 'export_tables_2026-09-09-01-50-37.sql';
    }
}
