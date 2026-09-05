<?php

namespace App\Command;

use App\Entity\ActeFinancier;
use App\Repository\ActeFinancierRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:facturation:seed-defaults',
    description: 'Crée les actes financiers par défaut (CONSULTATION en FC).',
)]
final class SeedFacturationDefaultsCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly ActeFinancierRepository $acteFinancierRepository,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $created = 0;

        if (null === $this->acteFinancierRepository->findOneBy(['code' => ActeFinancier::CODE_CONSULTATION])) {
            $acte = (new ActeFinancier())
                ->setCode(ActeFinancier::CODE_CONSULTATION)
                ->setLibelle('Consultation médicale')
                ->setTarif('0.0000')
                ->setUnite(ActeFinancier::UNITE_FC)
                ->setStatut(ActeFinancier::STATUT_ACTIF)
                ->setCreatedAt(new \DateTimeImmutable());

            $this->entityManager->persist($acte);
            ++$created;
        }

        if ($created > 0) {
            $this->entityManager->flush();
        }

        $io->success(sprintf(
            '%d acte(s) financier(s) créé(s). Acte CONSULTATION (%s) prêt pour génération automatique.',
            $created,
            ActeFinancier::UNITE_FC,
        ));

        return Command::SUCCESS;
    }
}
