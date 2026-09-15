<?php

namespace App\Command;

use App\Service\Personnel\ImportLegacyPersonnelService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

#[AsCommand(
    name: 'app:personnel:import-legacy-sigai',
    description: 'Importe les agents SIGAI (JSON mappé) vers le personnel CHU UKV.',
)]
final class ImportLegacyPersonnelCommand extends Command
{
    public function __construct(
        private readonly ImportLegacyPersonnelService $importLegacyPersonnelService,
        #[Autowire('%kernel.project_dir%')]
        private readonly string $projectDir,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('file', null, InputOption::VALUE_REQUIRED, 'Chemin vers sigai_agents_mapped.json')
            ->addOption('apply', null, InputOption::VALUE_NONE, 'Écrit en base (sinon simulation)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $requested = $input->getOption('file');
        $file = is_string($requested) && '' !== trim($requested)
            ? trim($requested)
            : $this->defaultJsonPath();
        $apply = (bool) $input->getOption('apply');

        if (!is_readable($file)) {
            $io->error(sprintf('Fichier introuvable : %s', $file));
            $io->writeln('Copiez le JSON sur le serveur, puis :');
            $io->writeln('  php bin/console app:personnel:import-legacy-sigai --file=/var/www/chu-ukv-nv/BACKEND/imports/sigai_agents_mapped.json');

            return Command::FAILURE;
        }

        $io->title($apply ? 'Import agents SIGAI (écriture)' : 'Import agents SIGAI (simulation)');
        $io->text($file);

        $result = $apply
            ? $this->importLegacyPersonnelService->importFromJson($file)
            : $this->importLegacyPersonnelService->previewFromJson($file);

        $io->table(
            ['Élément', 'Nombre'],
            [
                ['À créer / créés', (string) $result['created']],
                ['Ignorés (déjà en base ou doublon)', (string) $result['skipped']],
                ['Grades à créer / créés', (string) $result['grades']],
            ],
        );

        $rows = [];
        foreach (array_slice($result['actions'], 0, 40) as $action) {
            $rows[] = [
                (string) $action['sigaiId'],
                $action['nom'],
                $action['telephone'],
                $action['action'],
                $action['detail'],
            ];
        }
        if ($rows !== []) {
            $io->section('Détail (40 premières lignes)');
            $io->table(['Id SIGAI', 'Nom', 'Téléphone', 'Action', 'Détail'], $rows);
            if (count($result['actions']) > 40) {
                $io->text(sprintf('… %d ligne(s) de plus', count($result['actions']) - 40));
            }
        }

        if (!$apply) {
            $io->note('Aucune écriture. Relancer avec --apply pour créer les comptes.');
        } else {
            $io->success(sprintf('%d agent(s) créé(s), %d ignoré(s).', $result['created'], $result['skipped']));
        }

        return Command::SUCCESS;
    }

    private function defaultJsonPath(): string
    {
        $filename = 'sigai_agents_mapped.json';
        $candidates = [
            $this->projectDir . DIRECTORY_SEPARATOR . 'imports' . DIRECTORY_SEPARATOR . $filename,
            dirname($this->projectDir) . DIRECTORY_SEPARATOR . 'docs' . DIRECTORY_SEPARATOR . 'imports' . DIRECTORY_SEPARATOR . $filename,
            dirname($this->projectDir, 2) . DIRECTORY_SEPARATOR . 'docs' . DIRECTORY_SEPARATOR . 'imports' . DIRECTORY_SEPARATOR . $filename,
        ];

        foreach ($candidates as $candidate) {
            if (is_readable($candidate)) {
                return $candidate;
            }
        }

        return $candidates[0];
    }
}
