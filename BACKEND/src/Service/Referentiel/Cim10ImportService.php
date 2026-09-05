<?php

namespace App\Service\Referentiel;

use App\Entity\Maladie;
use App\Repository\MaladieRepository;
use Doctrine\ORM\EntityManagerInterface;

final class Cim10ImportService
{
    private const BATCH_SIZE = 250;

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly MaladieRepository $maladieRepository,
    ) {
    }

    /**
     * @return array{imported: int, skipped: int, truncated: bool}
     */
    public function importFromFile(string $filePath, bool $truncate = false): array
    {
        if (!is_file($filePath)) {
            throw new \InvalidArgumentException(sprintf('Fichier CIM-10 introuvable : %s', $filePath));
        }

        $wasTruncated = false;
        if ($truncate) {
            $this->truncate();
            $wasTruncated = true;
        }

        $existingCodes = $this->maladieRepository->findAllCodesIndexed();
        $imported = 0;
        $skipped = 0;
        $batchCount = 0;
        $now = new \DateTimeImmutable();

        foreach ($this->iterateRows($filePath) as $row) {
            $code = strtoupper(trim((string) ($row['code'] ?? '')));
            $libelle = trim((string) ($row['libelle'] ?? ''));

            if ('' === $code || '' === $libelle) {
                ++$skipped;
                continue;
            }

            if (isset($existingCodes[$code])) {
                ++$skipped;
                continue;
            }

            $maladie = (new Maladie())
                ->setCodeCim10($code)
                ->setLibelle(mb_substr($libelle, 0, 255))
                ->setChapitre($this->normalizeChapitre($row['chapitre'] ?? null))
                ->setCreatedAt($now);

            $this->entityManager->persist($maladie);
            $existingCodes[$code] = true;
            ++$imported;
            ++$batchCount;

            if ($batchCount >= self::BATCH_SIZE) {
                $this->entityManager->flush();
                $this->entityManager->clear(Maladie::class);
                $batchCount = 0;
            }
        }

        if ($batchCount > 0) {
            $this->entityManager->flush();
            $this->entityManager->clear(Maladie::class);
        }

        return [
            'imported' => $imported,
            'skipped' => $skipped,
            'truncated' => $wasTruncated,
        ];
    }

    private function truncate(): void
    {
        $this->entityManager->createQuery('DELETE FROM App\Entity\Maladie m')->execute();
        $this->entityManager->clear(Maladie::class);
    }

    private function normalizeChapitre(mixed $value): ?string
    {
        if (!is_string($value)) {
            return null;
        }

        $normalized = trim(str_replace(['[', ']'], '', $value));

        return '' === $normalized ? null : mb_substr($normalized, 0, 20);
    }

    /** @return \Generator<int, array<string, mixed>> */
    private function iterateRows(string $filePath): \Generator
    {
        if (str_ends_with(strtolower($filePath), '.gz')) {
            $handle = gzopen($filePath, 'rb');
            if (false === $handle) {
                throw new \RuntimeException(sprintf('Impossible d\'ouvrir %s', $filePath));
            }

            try {
                while (($line = gzgets($handle)) !== false) {
                    $row = json_decode(trim($line), true);
                    if (is_array($row)) {
                        yield $row;
                    }
                }
            } finally {
                gzclose($handle);
            }

            return;
        }

        $handle = fopen($filePath, 'rb');
        if (false === $handle) {
            throw new \RuntimeException(sprintf('Impossible d\'ouvrir %s', $filePath));
        }

        try {
            while (($line = fgets($handle)) !== false) {
                $row = json_decode(trim($line), true);
                if (is_array($row)) {
                    yield $row;
                }
            }
        } finally {
            fclose($handle);
        }
    }
}
