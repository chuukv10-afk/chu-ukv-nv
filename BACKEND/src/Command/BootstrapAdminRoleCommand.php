<?php

namespace App\Command;

use App\Entity\Personnel;
use App\Entity\PersonnelRole;
use App\Entity\Role;
use App\Repository\PersonnelRepository;
use App\Service\Role\RoleProvisioner;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:admin:bootstrap',
    description: 'Crée le rôle ADMIN, assure PERSONNEL, et affecte ADMIN à un personnel (périmètre GLOBAL)',
)]
final class BootstrapAdminRoleCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly PersonnelRepository $personnelRepository,
        private readonly RoleProvisioner $roleProvisioner,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument(
            'telephone',
            InputArgument::REQUIRED,
            'Téléphone du personnel à promouvoir administrateur',
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $telephone = (string) $input->getArgument('telephone');

        $personnel = $this->personnelRepository->findOneBy(['telephone' => $telephone]);
        if (null === $personnel) {
            $io->error(sprintf('Aucun personnel trouvé avec le téléphone "%s".', $telephone));

            return Command::FAILURE;
        }

        $this->roleProvisioner->assignDefaultPersonnelRole($personnel);
        $this->roleProvisioner->assignAdminRole($personnel);
        $this->entityManager->flush();

        $io->success(sprintf(
            'Rôles PERSONNEL et ADMIN affectés à %s %s (%s).',
            $personnel->getNom(),
            $personnel->getPostNom(),
            $telephone,
        ));

        $io->table(
            ['Rôle Symfony', 'Code', 'Périmètre'],
            [
                [Personnel::buildSymfonyRoleCode(Role::CODE_PERSONNEL), Role::CODE_PERSONNEL, PersonnelRole::PERIMETRE_GLOBAL],
                [Personnel::buildSymfonyRoleCode(Role::CODE_ADMIN), Role::CODE_ADMIN, PersonnelRole::PERIMETRE_GLOBAL],
            ],
        );

        return Command::SUCCESS;
    }
}
