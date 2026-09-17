<?php

namespace App\Command;

use App\Entity\PaiePeriode;
use App\Repository\PaiePeriodeRepository;
use App\Service\Rh\PaieService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:rh:paie-generer-brouillon',
    description: 'Régénère l’état de paie brouillon depuis le personnel et le barème courant.',
)]
final class GeneratePaieBrouillonCommand extends Command
{
    public function __construct(
        private readonly PaiePeriodeRepository $paiePeriodeRepository,
        private readonly PaieService $paieService,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $periode = $this->paiePeriodeRepository->findBrouillon();
        if (!$periode instanceof PaiePeriode) {
            $io->warning('Aucun état de paie brouillon à régénérer.');

            return Command::SUCCESS;
        }

        $this->paieService->generate($periode);
        $io->success(sprintf(
            'État %02d/%d régénéré. %s ligne(s) incluse(s), total %s.',
            $periode->getMois(),
            $periode->getAnnee(),
            $periode->getLignes()->filter(static fn ($ligne): bool => $ligne->isInclus())->count(),
            $periode->getTotalNet(),
        ));

        return Command::SUCCESS;
    }
}
