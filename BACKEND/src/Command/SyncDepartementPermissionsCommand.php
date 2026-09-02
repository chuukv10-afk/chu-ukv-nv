<?php

namespace App\Command;

use App\Service\Role\PermissionProvisioner;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:permissions:sync-departement',
    description: 'Crée les permissions Département et les lie aux rôles ADMIN (toutes) et PERSONNEL (lecture)',
)]
final class SyncDepartementPermissionsCommand extends Command
{
    public function __construct(
        private readonly PermissionProvisioner $permissionProvisioner,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $createdCount = $this->permissionProvisioner->syncDepartementPermissions();

        $io->success(sprintf(
            'Permissions Département synchronisées. %d nouvelle(s) permission(s) créée(s).',
            $createdCount,
        ));

        $io->listing([
            'organisation.departement.read   → ADMIN + PERSONNEL',
            'organisation.departement.create → ADMIN',
            'organisation.departement.update → ADMIN',
            'organisation.departement.delete → ADMIN',
        ]);

        return Command::SUCCESS;
    }
}
