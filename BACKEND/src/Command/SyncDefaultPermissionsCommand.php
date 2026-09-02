<?php

namespace App\Command;

use App\Service\Role\PermissionProvisioner;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:permissions:sync-defaults',
    description: 'Crée toutes les permissions CRUD (Organisation, Référentiel, Clinique) et les lie aux rôles',
)]
final class SyncDefaultPermissionsCommand extends Command
{
    public function __construct(
        private readonly PermissionProvisioner $permissionProvisioner,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $createdCount = $this->permissionProvisioner->syncAll();

        $io->success(sprintf(
            'Permissions synchronisées. %d nouvelle(s) permission(s) créée(s).',
            $createdCount,
        ));

        $io->text('Règle d\'affectation :');
        $io->listing([
            'ADMIN     → toutes les permissions',
            'PERSONNEL → permissions *.read uniquement',
        ]);

        $io->text('Modules couverts : Organisation (5), Référentiel (4), Clinique (1) — 40 permissions au total.');

        return Command::SUCCESS;
    }
}
