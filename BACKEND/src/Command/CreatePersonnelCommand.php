<?php

namespace App\Command;

use App\Entity\Personnel;
use App\Repository\PersonnelRepository;
use App\Service\Role\RoleProvisioner;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[AsCommand(
    name: 'app:personnel:create',
    description: 'Crée un compte personnel pour tester l\'authentification JWT',
)]
class CreatePersonnelCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly UserPasswordHasherInterface $passwordHasher,
        private readonly PersonnelRepository $personnelRepository,
        private readonly RoleProvisioner $roleProvisioner,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('telephone', InputArgument::REQUIRED, 'Numéro de téléphone (identifiant de connexion)')
            ->addArgument('password', InputArgument::REQUIRED, 'Mot de passe')
            ->addArgument('matricule', InputArgument::REQUIRED, 'Matricule')
            ->addArgument('nom', InputArgument::REQUIRED, 'Nom')
            ->addArgument('postNom', InputArgument::REQUIRED, 'Post-nom');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $telephone = (string) $input->getArgument('telephone');
        $matricule = (string) $input->getArgument('matricule');

        if ($this->personnelRepository->findOneBy(['telephone' => $telephone])) {
            $io->error(sprintf('Un personnel avec le téléphone "%s" existe déjà.', $telephone));

            return Command::FAILURE;
        }

        if ($this->personnelRepository->findOneBy(['matricule' => $matricule])) {
            $io->error(sprintf('Un personnel avec le matricule "%s" existe déjà.', $matricule));

            return Command::FAILURE;
        }

        $personnel = (new Personnel())
            ->setTelephone($telephone)
            ->setMatricule($matricule)
            ->setNom((string) $input->getArgument('nom'))
            ->setPostNom((string) $input->getArgument('postNom'))
            ->setSexe('M')
            ->setType('MEDICAL')
            ->setStatus(Personnel::STATUS_ACTIF)
            ->setCreatedAt(new \DateTimeImmutable());

        $personnel->setPassword($this->passwordHasher->hashPassword(
            $personnel,
            (string) $input->getArgument('password')
        ));

        $this->entityManager->persist($personnel);
        $this->roleProvisioner->assignDefaultPersonnelRole($personnel);
        $this->entityManager->flush();

        $io->success(sprintf(
            'Personnel créé (id: %s, téléphone: %s, matricule: %s).',
            $personnel->getId()?->toRfc4122(),
            $telephone,
            $matricule
        ));

        return Command::SUCCESS;
    }
}
