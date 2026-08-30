<?php

namespace App\Command;

use App\Entity\Personnel;
use App\Entity\Role;
use App\Repository\PersonnelRepository;
use App\Service\Role\RoleProvisioner;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:roles:sync-defaults',
    description: 'Crée les rôles de base en base et affecte PERSONNEL à tout le personnel sans ce rôle',
)]
final class SyncDefaultRolesCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly PersonnelRepository $personnelRepository,
        private readonly RoleProvisioner $roleProvisioner,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $this->roleProvisioner->findOrCreate(Role::CODE_PERSONNEL, 'Personnel');
        $this->roleProvisioner->findOrCreate(Role::CODE_ADMIN, 'Administrateur');
        $this->entityManager->flush();

        $assignedCount = 0;
        foreach ($this->personnelRepository->findAll() as $personnel) {
            if (!$this->roleProvisioner->hasRole($personnel, Role::CODE_PERSONNEL)) {
                $this->roleProvisioner->assignDefaultPersonnelRole($personnel);
                ++$assignedCount;
            }
        }

        $this->entityManager->flush();

        $io->success(sprintf(
            'Rôles de base synchronisés. %d affectation(s) PERSONNEL ajoutée(s).',
            $assignedCount,
        ));

        return Command::SUCCESS;
    }
}
