<?php

namespace App\Service\Rh;

use App\Entity\BaremePrime;
use App\Entity\Fonction;
use App\Entity\Grade;
use App\Exception\ConflictException;
use App\Exception\NotFoundException;
use App\Referentiel\BaremePrimeCatalog;
use App\Repository\BaremePrimeRepository;
use App\Repository\FonctionRepository;
use App\Repository\GradeRepository;
use Doctrine\ORM\EntityManagerInterface;

final class BaremePrimeService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly BaremePrimeRepository $baremePrimeRepository,
        private readonly GradeRepository $gradeRepository,
        private readonly FonctionRepository $fonctionRepository,
    ) {
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function list(): array
    {
        return array_map([$this, 'serialize'], $this->baremePrimeRepository->findAllOrdered());
    }

    /**
     * @return list<BaremePrime>
     */
    public function currentRates(): array
    {
        return $this->baremePrimeRepository->findAllOrdered();
    }

    /**
     * @return array{grades: list<array<string, mixed>>, fonctions: list<array<string, mixed>>}
     */
    public function lookups(): array
    {
        $grades = [];
        foreach ($this->gradeRepository->findBy([], ['libelle' => 'ASC']) as $grade) {
            $grades[] = [
                'id' => $grade->getId(),
                'code' => $grade->getCode(),
                'libelle' => $grade->getLibelle(),
            ];
        }

        $fonctions = [];
        foreach ($this->fonctionRepository->findBy([], ['libelle' => 'ASC']) as $fonction) {
            $fonctions[] = [
                'id' => $fonction->getId(),
                'code' => $fonction->getCode(),
                'libelle' => $fonction->getLibelle(),
            ];
        }

        return ['grades' => $grades, 'fonctions' => $fonctions];
    }

    public function upsert(?int $gradeId, int $fonctionId, string $montant): BaremePrime
    {
        $grade = null;
        if (null !== $gradeId) {
            $grade = $this->gradeRepository->find($gradeId);
            if (!$grade instanceof Grade) {
                throw new NotFoundException('Grade non trouvé.');
            }
        }

        $fonction = $this->fonctionRepository->find($fonctionId);
        if (!$fonction instanceof Fonction) {
            throw new NotFoundException('Fonction non trouvée.');
        }

        $existing = $this->baremePrimeRepository->findOneByGradeAndFonction($grade, $fonction);
        if (null === $existing) {
            $existing = (new BaremePrime())
                ->setGrade($grade)
                ->setFonction($fonction)
                ->setDateEffet(new \DateTimeImmutable('2026-08-01'))
                ->setCreatedAt(new \DateTimeImmutable());
            $this->entityManager->persist($existing);
        }

        $existing->setMontant(number_format((float) str_replace(',', '.', $montant), 2, '.', ''));
        $this->entityManager->flush();

        return $existing;
    }

    public function update(int $id, ?int $gradeId, int $fonctionId, string $montant): BaremePrime
    {
        $bareme = $this->baremePrimeRepository->find($id);
        if (!$bareme instanceof BaremePrime) {
            throw new NotFoundException('Barème non trouvé.');
        }

        $grade = null;
        if (null !== $gradeId) {
            $grade = $this->gradeRepository->find($gradeId);
            if (!$grade instanceof Grade) {
                throw new NotFoundException('Grade non trouvé.');
            }
        }

        $fonction = $this->fonctionRepository->find($fonctionId);
        if (!$fonction instanceof Fonction) {
            throw new NotFoundException('Fonction non trouvée.');
        }

        $duplicate = $this->baremePrimeRepository->findOneByGradeAndFonction($grade, $fonction);
        if (null !== $duplicate && $duplicate->getId() !== $bareme->getId()) {
            throw new ConflictException('Ce couple grade + fonction existe déjà dans le barème.');
        }

        $bareme
            ->setGrade($grade)
            ->setFonction($fonction)
            ->setMontant(number_format((float) str_replace(',', '.', $montant), 2, '.', ''));
        $this->entityManager->flush();

        return $bareme;
    }

    /**
     * @return list<string>
     */
    public function exportHeaders(): array
    {
        return ['N°', 'Grade', 'Fonction', 'Montant'];
    }

    /**
     * @return list<list<string>>
     */
    public function exportRows(): array
    {
        $rows = [];
        foreach ($this->baremePrimeRepository->findAllOrdered() as $bareme) {
            $rows[] = [
                $bareme->getGrade()?->getLibelle() ?? 'Toutes (sans grade)',
                (string) ($bareme->getFonction()?->getLibelle() ?? ''),
                number_format((float) ($bareme->getMontant() ?? '0'), 0, ',', ' '),
            ];
        }

        return $rows;
    }

    public function exportTitle(): string
    {
        return 'Barème de la prime locale';
    }

    public function exportFilenamePrefix(): string
    {
        return 'bareme-prime-locale';
    }

    public function delete(int $id): void
    {
        $bareme = $this->baremePrimeRepository->find($id);
        if (!$bareme instanceof BaremePrime) {
            throw new NotFoundException('Barème non trouvé.');
        }

        $this->entityManager->remove($bareme);
        $this->entityManager->flush();
    }

    /**
     * @return array{created: int, updated: int, skipped: int}
     */
    public function syncCatalog(): array
    {
        $created = 0;
        $updated = 0;
        $skipped = 0;
        $dateEffet = new \DateTimeImmutable('2026-08-01');

        foreach (BaremePrimeCatalog::definitions() as $definition) {
            $fonction = $this->fonctionRepository->findOneBy(['code' => $definition['fonctionCode']]);
            if (!$fonction instanceof Fonction) {
                ++$skipped;
                continue;
            }

            $grade = null;
            if (null !== $definition['gradeCode'] && '' !== $definition['gradeCode']) {
                $grade = $this->findOrCreateGrade($definition['gradeCode'], $definition['gradeLibelle']);
            }

            $existing = $this->baremePrimeRepository->findOneByGradeAndFonction($grade, $fonction);
            if (null === $existing) {
                $existing = (new BaremePrime())
                    ->setGrade($grade)
                    ->setFonction($fonction)
                    ->setDateEffet($dateEffet)
                    ->setCreatedAt(new \DateTimeImmutable());
                $this->entityManager->persist($existing);
                ++$created;
            } else {
                ++$updated;
            }

            $existing->setMontant($definition['montant']);
        }

        $this->entityManager->flush();

        return ['created' => $created, 'updated' => $updated, 'skipped' => $skipped];
    }

    public function resolveMontant(?Grade $grade, ?Fonction $fonction): ?string
    {
        if (!$fonction instanceof Fonction) {
            return null;
        }

        if ($grade instanceof Grade) {
            $exact = $this->baremePrimeRepository->findOneByGradeAndFonction($grade, $fonction);
            if (null !== $exact) {
                return $exact->getMontant();
            }
        }

        $fallback = $this->baremePrimeRepository->findOneByGradeAndFonction(null, $fonction);

        return $fallback?->getMontant();
    }

    /**
     * @return array<string, mixed>
     */
    public function serialize(BaremePrime $bareme): array
    {
        $grade = $bareme->getGrade();
        $fonction = $bareme->getFonction();

        return [
            'id' => $bareme->getId(),
            'montant' => $bareme->getMontant(),
            'dateEffet' => $bareme->getDateEffet()?->format('Y-m-d'),
            'grade' => null !== $grade ? [
                'id' => $grade->getId(),
                'code' => $grade->getCode(),
                'libelle' => $grade->getLibelle(),
            ] : null,
            'fonction' => null !== $fonction ? [
                'id' => $fonction->getId(),
                'code' => $fonction->getCode(),
                'libelle' => $fonction->getLibelle(),
            ] : null,
        ];
    }

    private function findOrCreateGrade(string $code, string $libelle): Grade
    {
        $existing = $this->gradeRepository->findOneBy(['code' => $code]);
        if ($existing instanceof Grade) {
            return $existing;
        }

        foreach ($this->gradeRepository->findAll() as $grade) {
            if (self::normalize($grade->getCode() ?? '') === self::normalize($code)
                || self::normalize($grade->getLibelle() ?? '') === self::normalize($libelle)
            ) {
                return $grade;
            }
        }

        $grade = (new Grade())
            ->setCode(substr($code, 0, 8))
            ->setLibelle('' !== trim($libelle) ? $libelle : $code)
            ->setCreatedAt(new \DateTimeImmutable());
        $this->entityManager->persist($grade);
        $this->entityManager->flush();

        return $grade;
    }

    private static function normalize(string $value): string
    {
        $folded = $value;
        if (\class_exists(\Transliterator::class)) {
            $folded = \Transliterator::create('NFD; [:Nonspacing Mark:] Remove; NFC')?->transliterate($value) ?? $value;
        } elseif (\function_exists('iconv')) {
            $folded = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value) ?: $value;
        }

        return strtolower((string) preg_replace('/[^a-z0-9]+/i', '', $folded));
    }
}
