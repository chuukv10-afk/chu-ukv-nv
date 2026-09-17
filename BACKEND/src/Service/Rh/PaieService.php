<?php

namespace App\Service\Rh;

use App\DTO\Rh\UpdatePaieLigneInput;
use App\Entity\PaieBaremeApplique;
use App\Entity\PaieLigne;
use App\Entity\PaiePeriode;
use App\Entity\Personnel;
use App\Exception\ConflictException;
use App\Exception\NotFoundException;
use App\Repository\PaieLigneRepository;
use App\Repository\PaiePeriodeRepository;
use App\Repository\PersonnelRepository;
use Doctrine\ORM\EntityManagerInterface;

final class PaieService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly PaiePeriodeRepository $paiePeriodeRepository,
        private readonly PaieLigneRepository $paieLigneRepository,
        private readonly PersonnelRepository $personnelRepository,
        private readonly BaremePrimeService $baremePrimeService,
    ) {
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listPeriodes(): array
    {
        return array_map([$this, 'serializePeriode'], $this->paiePeriodeRepository->findAllOrdered());
    }

    public function getPeriode(int $id): PaiePeriode
    {
        $periode = $this->paiePeriodeRepository->find($id);
        if (!$periode instanceof PaiePeriode) {
            throw new NotFoundException('Mois de paie introuvable.');
        }

        return $periode;
    }

    public function openPeriode(int $annee, int $mois, Personnel $acteur): PaiePeriode
    {
        $existing = $this->paiePeriodeRepository->findOneBy(['annee' => $annee, 'mois' => $mois]);
        if ($existing instanceof PaiePeriode) {
            throw new ConflictException(sprintf('Le mois de %s existe déjà.', $this->libelleMois($annee, $mois)));
        }

        $brouillon = $this->paiePeriodeRepository->findBrouillon();
        if ($brouillon instanceof PaiePeriode) {
            throw new ConflictException(sprintf(
                'Le mois de %s est encore ouvert. Clôturez-le avant d’en préparer un autre.',
                $this->libellePeriode($brouillon),
            ));
        }

        $periode = (new PaiePeriode())
            ->setAnnee($annee)
            ->setMois($mois)
            ->setStatut(PaiePeriode::STATUT_BROUILLON)
            ->setCreatedBy($acteur)
            ->setCreatedAt(new \DateTimeImmutable());

        $this->entityManager->persist($periode);
        $this->entityManager->flush();

        $this->generate($periode);

        return $periode;
    }

    public function generate(PaiePeriode $periode): PaiePeriode
    {
        $this->assertBrouillon($periode);

        $this->captureBareme($periode);

        $existingByPersonnelId = [];
        foreach ($periode->getLignes() as $ligne) {
            $personnel = $ligne->getPersonnel();
            if (null !== $personnel) {
                $existingByPersonnelId[$personnel->getId()?->toRfc4122()] = $ligne;
            }
        }

        $seen = [];
        foreach ($this->personnelRepository->findAllForPaie() as $personnel) {
            $personnelId = $personnel->getId()?->toRfc4122();
            if (null === $personnelId) {
                continue;
            }
            $seen[$personnelId] = true;
            $ligne = $existingByPersonnelId[$personnelId] ?? null;
            if (null === $ligne) {
                $ligne = (new PaieLigne())
                    ->setPeriode($periode)
                    ->setPersonnel($personnel);
                $periode->addLigne($ligne);
                $this->entityManager->persist($ligne);
            }

            $this->refreshSnapshot($ligne, $personnel);

            $propose = $this->resolveMontantFromSnapshot($periode, $personnel->getGrade(), $personnel->getFonction());
            $ligne->setMontantPropose($propose);

            if (PaieLigne::SOURCE_MANUEL === $ligne->getSource()) {
                continue;
            }

            $this->applyBareme($ligne, $personnel, $propose);
        }

        foreach ($periode->getLignes()->toArray() as $ligne) {
            $personnel = $ligne->getPersonnel();
            $personnelId = $personnel?->getId()?->toRfc4122();
            if (null !== $personnelId && isset($seen[$personnelId])) {
                continue;
            }
            if (PaieLigne::SOURCE_MANUEL === $ligne->getSource() && $ligne->isInclus()) {
                continue;
            }
            $periode->removeLigne($ligne);
            $this->entityManager->remove($ligne);
        }

        $periode->setGeneratedAt(new \DateTimeImmutable());
        $this->recalculateTotal($periode);
        $this->entityManager->flush();

        return $periode;
    }

    public function updateLigne(PaiePeriode $periode, int $ligneId, UpdatePaieLigneInput $input): PaieLigne
    {
        $this->assertBrouillon($periode);

        $ligne = $this->paieLigneRepository->find($ligneId);
        if (!$ligne instanceof PaieLigne || $ligne->getPeriode()?->getId() !== $periode->getId()) {
            throw new NotFoundException('Ligne de paie non trouvée.');
        }

        if (null !== $input->inclus) {
            $ligne->setInclus($input->inclus);
            if (!$input->inclus && PaieLigne::SOURCE_BAREME === $ligne->getSource()) {
                $ligne->setSource(PaieLigne::SOURCE_MANUEL);
                $ligne->setMotifCode('EXCLU');
            }
            if ($input->inclus && PaieLigne::SOURCE_AUCUN === $ligne->getSource()) {
                $ligne->setSource(PaieLigne::SOURCE_MANUEL);
                $ligne->setMotifCode('MANUEL');
            }
        }

        if (null !== $input->montant) {
            $ligne->setMontant(number_format((float) str_replace(',', '.', $input->montant), 2, '.', ''));
        }

        if (null !== $input->motif) {
            $ligne->setMotif('' !== trim($input->motif) ? trim($input->motif) : null);
        }

        if ($ligne->isInclus() && (float) $ligne->getMontant() <= 0) {
            throw new ConflictException('Indiquez un montant pour payer cet agent.');
        }

        if ($ligne->isInclus() && null === $ligne->getMontantPropose()) {
            $this->rememberRateOnPeriode($periode, $ligne);
        }

        if ($ligne->isInclus() && $this->sameAmount($ligne->getMontant(), $ligne->getMontantPropose())) {
            $ligne->setSource(PaieLigne::SOURCE_BAREME)->setMotifCode(null)->setMotif(null);
        } elseif ($ligne->isInclus()) {
            $ligne->setSource(PaieLigne::SOURCE_MANUEL)->setMotifCode('MANUEL');
            if (null === $ligne->getMotif() || '' === trim((string) $ligne->getMotif())) {
                $ligne->setMotif('Montant ajusté');
            }
        }

        if (!$ligne->isInclus()) {
            $ligne->setMontant('0.00');
            $ligne->setSource(PaieLigne::SOURCE_MANUEL);
            $ligne->setMotifCode('EXCLU');
            if (null === $ligne->getMotif() || '' === trim((string) $ligne->getMotif())) {
                $ligne->setMotif('Non payé ce mois-ci');
            }
        }

        $this->recalculateTotal($periode);
        $this->entityManager->flush();

        return $ligne;
    }

    public function validate(PaiePeriode $periode, Personnel $acteur): PaiePeriode
    {
        $this->assertBrouillon($periode);

        $inclus = 0;
        foreach ($periode->getLignes() as $ligne) {
            if ($ligne->isInclus()) {
                ++$inclus;
            }
        }
        if (0 === $inclus) {
            throw new ConflictException('Impossible de clôturer : aucun agent n’est payé.');
        }

        $periode
            ->setStatut(PaiePeriode::STATUT_VALIDE)
            ->setValidePar($acteur)
            ->setValideAt(new \DateTimeImmutable());
        $this->recalculateTotal($periode);
        $this->entityManager->flush();

        return $periode;
    }

    /**
     * @return array<string, mixed>
     */
    public function serializePeriode(PaiePeriode $periode, bool $withLignes = false): array
    {
        $inclus = 0;
        $aTraiter = 0;
        foreach ($periode->getLignes() as $ligne) {
            if ($ligne->isInclus()) {
                ++$inclus;
            } else {
                ++$aTraiter;
            }
        }

        $payload = [
            'id' => $periode->getId(),
            'annee' => $periode->getAnnee(),
            'mois' => $periode->getMois(),
            'libelle' => $this->libellePeriode($periode),
            'statut' => $periode->getStatut(),
            'totalNet' => $periode->getTotalNet(),
            'nbLignesIncluses' => $inclus,
            'nbLignesATraiter' => $aTraiter,
            'generatedAt' => $periode->getGeneratedAt()?->format(\DateTimeInterface::ATOM),
            'valideAt' => $periode->getValideAt()?->format(\DateTimeInterface::ATOM),
            'createdAt' => $periode->getCreatedAt()?->format(\DateTimeInterface::ATOM),
            'createdBy' => $this->serializePersonnelRef($periode->getCreatedBy()),
            'validePar' => $this->serializePersonnelRef($periode->getValidePar()),
        ];

        if ($withLignes) {
            $lignesIncluses = [];
            $lignesATraiter = [];
            $lignes = [];
            foreach ($this->paieLigneRepository->findByPeriodeOrdered($periode) as $ligne) {
                $row = $this->serializeLigne($ligne);
                $lignes[] = $row;
                if ($ligne->isInclus()) {
                    $lignesIncluses[] = $row;
                } else {
                    $lignesATraiter[] = $row;
                }
            }
            $payload['lignes'] = $lignes;
            $payload['lignesIncluses'] = $lignesIncluses;
            $payload['lignesATraiter'] = $lignesATraiter;
            $baremes = $periode->getBaremesAppliques()->toArray();
            usort($baremes, static function (PaieBaremeApplique $left, PaieBaremeApplique $right): int {
                $byFonction = strcasecmp((string) $left->getFonctionLibelle(), (string) $right->getFonctionLibelle());
                if (0 !== $byFonction) {
                    return $byFonction;
                }

                return strcasecmp((string) $left->getGradeLibelle(), (string) $right->getGradeLibelle());
            });
            $payload['baremeApplique'] = array_map([$this, 'serializeBaremeApplique'], $baremes);
            $payload['baremeCaptureAt'] = $periode->getGeneratedAt()?->format(\DateTimeInterface::ATOM);
        }

        return $payload;
    }

    /**
     * @return array<string, mixed>
     */
    public function serializeLigne(PaieLigne $ligne): array
    {
        return [
            'id' => $ligne->getId(),
            'personnelId' => $ligne->getPersonnel()?->getId()?->toRfc4122(),
            'matricule' => $ligne->getMatricule(),
            'nomComplet' => $ligne->getNomComplet(),
            'gradeLibelle' => $ligne->getGradeLibelle(),
            'fonctionLibelle' => $ligne->getFonctionLibelle(),
            'gradeId' => $ligne->getPersonnel()?->getGrade()?->getId(),
            'fonctionId' => $ligne->getPersonnel()?->getFonction()?->getId(),
            'serviceLibelle' => $ligne->getServiceLibelle(),
            'statutPersonnel' => $ligne->getStatutPersonnel(),
            'montant' => $ligne->getMontant(),
            'montantPropose' => $ligne->getMontantPropose(),
            'ajuste' => $ligne->isInclus() && (null === $ligne->getMontantPropose() || !$this->sameAmount($ligne->getMontant(), $ligne->getMontantPropose())),
            'source' => $ligne->getSource(),
            'inclus' => $ligne->isInclus(),
            'motifCode' => $ligne->getMotifCode(),
            'motif' => $ligne->getMotif(),
            'etat' => $this->etatLigne($ligne),
        ];
    }

    /**
     * @return list<list<string>>
     */
    public function exportRows(PaiePeriode $periode): array
    {
        $rows = [];
        foreach ($this->paieLigneRepository->findByPeriodeOrdered($periode) as $ligne) {
            if (!$ligne->isInclus()) {
                continue;
            }
            $rows[] = [
                (string) ($ligne->getNomComplet() ?? ''),
                (string) ($ligne->getGradeLibelle() ?? ''),
                (string) ($ligne->getFonctionLibelle() ?? ''),
                $this->formatMontant($ligne->getMontant()),
            ];
        }

        $rows[] = ['TOTAL', '', '', $this->formatMontant($periode->getTotalNet())];

        return $rows;
    }

    public function exportTitle(PaiePeriode $periode): string
    {
        return sprintf('État de la prime locale — %s', $this->libellePeriode($periode));
    }

    public function exportFilenamePrefix(PaiePeriode $periode): string
    {
        return sprintf('prime-locale-%d-%02d', $periode->getAnnee(), $periode->getMois());
    }

    /**
     * @return list<string>
     */
    public function exportHeaders(): array
    {
        return ['N°', 'Nom', 'Grade', 'Fonction', 'Net'];
    }

    public function libellePeriode(PaiePeriode $periode): string
    {
        return $this->libelleMois($periode->getAnnee(), $periode->getMois());
    }

    private function libelleMois(int $annee, int $mois): string
    {
        $labels = [
            1 => 'Janvier', 2 => 'Février', 3 => 'Mars', 4 => 'Avril',
            5 => 'Mai', 6 => 'Juin', 7 => 'Juillet', 8 => 'Août',
            9 => 'Septembre', 10 => 'Octobre', 11 => 'Novembre', 12 => 'Décembre',
        ];

        return sprintf('%s %d', $labels[$mois] ?? sprintf('%02d', $mois), $annee);
    }

    private function applyBareme(PaieLigne $ligne, Personnel $personnel, ?string $montantPropose): void
    {
        $statut = $personnel->getStatus();
        $grade = $personnel->getGrade();
        $fonction = $personnel->getFonction();

        if (Personnel::STATUS_ACTIF !== $statut) {
            $ligne->setInclus(false)->setMontant('0.00')->setSource(PaieLigne::SOURCE_AUCUN)->setMotifCode('STATUT');

            return;
        }
        if (null === $grade) {
            $ligne->setInclus(false)->setMontant('0.00')->setSource(PaieLigne::SOURCE_AUCUN)->setMotifCode('SANS_GRADE');

            return;
        }
        if (null === $fonction) {
            $ligne->setInclus(false)->setMontant('0.00')->setSource(PaieLigne::SOURCE_AUCUN)->setMotifCode('SANS_FONCTION');

            return;
        }
        if (null === $montantPropose) {
            $ligne->setInclus(false)->setMontant('0.00')->setSource(PaieLigne::SOURCE_AUCUN)->setMotifCode('SANS_BAREME');

            return;
        }

        $ligne
            ->setInclus(true)
            ->setMontant($montantPropose)
            ->setSource(PaieLigne::SOURCE_BAREME)
            ->setMotifCode(null)
            ->setMotif(null);
    }

    private function captureBareme(PaiePeriode $periode): void
    {
        foreach ($periode->getBaremesAppliques()->toArray() as $row) {
            $periode->removeBaremeApplique($row);
            $this->entityManager->remove($row);
        }

        foreach ($this->baremePrimeService->currentRates() as $rate) {
            $fonction = $rate->getFonction();
            if (null === $fonction) {
                continue;
            }

            $snapshot = (new PaieBaremeApplique())
                ->setPeriode($periode)
                ->setGrade($rate->getGrade())
                ->setFonction($fonction)
                ->setGradeLibelle($rate->getGrade()?->getLibelle())
                ->setFonctionLibelle((string) $fonction->getLibelle())
                ->setMontant((string) $rate->getMontant());
            $periode->addBaremeApplique($snapshot);
            $this->entityManager->persist($snapshot);
        }
    }

    private function resolveMontantFromSnapshot(PaiePeriode $periode, mixed $grade, mixed $fonction): ?string
    {
        if (!$fonction instanceof \App\Entity\Fonction) {
            return null;
        }

        $fonctionId = $fonction->getId();
        $gradeId = $grade instanceof \App\Entity\Grade ? $grade->getId() : null;
        $exact = null;
        $fallback = null;

        foreach ($periode->getBaremesAppliques() as $row) {
            $rowFonctionId = $row->getFonction()?->getId();
            if ($rowFonctionId !== $fonctionId) {
                continue;
            }

            $rowGradeId = $row->getGrade()?->getId();
            if (null !== $gradeId && $rowGradeId === $gradeId) {
                $exact = $row->getMontant();
                break;
            }
            if (null === $rowGradeId) {
                $fallback = $row->getMontant();
            }
        }

        return $exact ?? $fallback;
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeBaremeApplique(PaieBaremeApplique $bareme): array
    {
        return [
            'id' => $bareme->getId(),
            'gradeLibelle' => $bareme->getGradeLibelle(),
            'fonctionLibelle' => $bareme->getFonctionLibelle(),
            'montant' => $bareme->getMontant(),
        ];
    }

    private function rememberRateOnPeriode(PaiePeriode $periode, PaieLigne $ligne): void
    {
        $personnel = $ligne->getPersonnel();
        $fonction = $personnel?->getFonction();
        if (null === $personnel || null === $fonction) {
            return;
        }

        $grade = $personnel->getGrade();
        $montant = $ligne->getMontant();
        $this->baremePrimeService->upsert($grade?->getId(), (int) $fonction->getId(), $montant);

        $matched = false;
        foreach ($periode->getBaremesAppliques() as $row) {
            $sameFonction = $row->getFonction()?->getId() === $fonction->getId();
            $sameGrade = $row->getGrade()?->getId() === $grade?->getId();
            if ($sameFonction && $sameGrade) {
                $row->setMontant($montant);
                $matched = true;
                break;
            }
        }

        if (!$matched) {
            $snapshot = (new PaieBaremeApplique())
                ->setPeriode($periode)
                ->setGrade($grade)
                ->setFonction($fonction)
                ->setGradeLibelle($grade?->getLibelle())
                ->setFonctionLibelle((string) $fonction->getLibelle())
                ->setMontant($montant);
            $periode->addBaremeApplique($snapshot);
            $this->entityManager->persist($snapshot);
        }

        $ligne->setMontantPropose($montant);
    }

    private function etatLigne(PaieLigne $ligne): string
    {
        if ($ligne->isInclus()) {
            return $this->sameAmount($ligne->getMontant(), $ligne->getMontantPropose()) ? 'ok' : 'ajuste';
        }

        return match ($ligne->getMotifCode()) {
            'SANS_FONCTION' => 'sans_fonction',
            'SANS_GRADE' => 'sans_grade',
            'SANS_BAREME' => 'sans_bareme',
            'STATUT' => 'inactif',
            default => 'exclu',
        };
    }

    private function sameAmount(?string $left, ?string $right): bool
    {
        if (null === $left || null === $right || '' === $left || '' === $right) {
            return false;
        }

        return abs((float) $left - (float) $right) < 0.005;
    }

    private function refreshSnapshot(PaieLigne $ligne, Personnel $personnel): void
    {
        $ligne
            ->setNomComplet($this->formatFullName($personnel))
            ->setMatricule($personnel->getMatricule())
            ->setGradeLibelle($personnel->getGrade()?->getLibelle())
            ->setFonctionLibelle($personnel->getFonction()?->getLibelle())
            ->setServiceLibelle($personnel->getService()?->getLibelle())
            ->setStatutPersonnel($personnel->getStatus());
    }

    private function recalculateTotal(PaiePeriode $periode): void
    {
        $total = 0.0;
        foreach ($periode->getLignes() as $ligne) {
            if ($ligne->isInclus()) {
                $total += (float) $ligne->getMontant();
            }
        }
        $periode->setTotalNet(number_format($total, 2, '.', ''));
    }

    private function assertBrouillon(PaiePeriode $periode): void
    {
        if (PaiePeriode::STATUT_BROUILLON !== $periode->getStatut()) {
            throw new ConflictException('Ce mois est déjà clôturé. Plus aucune modification n’est possible.');
        }
    }

    /**
     * @return array<string, mixed>|null
     */
    private function serializePersonnelRef(?Personnel $personnel): ?array
    {
        if (!$personnel instanceof Personnel) {
            return null;
        }

        return [
            'id' => $personnel->getId()?->toRfc4122(),
            'nomComplet' => $this->formatFullName($personnel),
            'matricule' => $personnel->getMatricule(),
        ];
    }

    private function formatFullName(Personnel $personnel): string
    {
        $parts = array_filter([
            $personnel->getPrenom(),
            $personnel->getNom(),
            $personnel->getPostNom(),
        ], static fn (?string $part): bool => null !== $part && '' !== trim($part));

        $fullName = trim(implode(' ', $parts));

        return '' !== $fullName ? $fullName : (string) ($personnel->getMatricule() ?? '');
    }

    private function formatMontant(?string $montant): string
    {
        return number_format((float) ($montant ?? '0'), 0, ',', ' ');
    }
}
