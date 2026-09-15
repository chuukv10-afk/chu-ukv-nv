<?php

namespace App\Service\Facturation;

use App\Entity\ActeFinancier;
use App\Exception\ConflictException;
use App\Repository\ActeFinancierRepository;
use Doctrine\ORM\EntityManagerInterface;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

final class GrilleTarifaireImportService
{
    private const SHEET_NAME = 'Grille CHHU';
    private const HEADER_ROW = 3;
    private const FIRST_DATA_ROW = 4;

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly ActeFinancierRepository $acteFinancierRepository,
        private readonly TarificationService $tarificationService,
    ) {
    }

    /**
     * @return array{imported: int, updated: int, skipped: int, total: int}
     */
    public function importFromFile(string $path): array
    {
        if (!is_file($path) || !is_readable($path)) {
            throw new ConflictException(sprintf('Fichier introuvable ou illisible : %s', $path));
        }

        $spreadsheet = IOFactory::load($path);
        $sheet = $spreadsheet->getSheetByName(self::SHEET_NAME);
        if (null === $sheet) {
            throw new ConflictException(sprintf('Feuille « %s » introuvable dans le fichier.', self::SHEET_NAME));
        }

        $this->assertHeader($sheet);

        $imported = 0;
        $updated = 0;
        $skipped = 0;

        for ($row = self::FIRST_DATA_ROW; $row <= $sheet->getHighestDataRow(); ++$row) {
            $service = $this->cellString($sheet, $row, 2);
            $libelle = $this->cellString($sheet, $row, 4);
            if ('' === $service || '' === $libelle) {
                ++$skipped;
                continue;
            }

            $sousCategorie = $this->cellString($sheet, $row, 3);
            $tarifA = $this->cellMoney($sheet, $row, 5);
            $tarifA0 = $this->cellMoney($sheet, $row, 7);
            $tarifA1 = $this->cellMoney($sheet, $row, 8);
            $tarifB = $this->cellMoney($sheet, $row, 9);
            $tarifC = $this->cellMoney($sheet, $row, 10);

            $acte = $this->acteFinancierRepository->findOneBy([
                'serviceGrille' => $service,
                'libelle' => $libelle,
            ]);
            $isNew = null === $acte;
            if ($isNew) {
                $acte = (new ActeFinancier())
                    ->setCode($this->buildCode($service, $libelle))
                    ->setCreatedAt(new \DateTimeImmutable())
                    ->setUnite(ActeFinancier::UNITE_FC)
                    ->setStatut(ActeFinancier::STATUT_ACTIF);
                $this->entityManager->persist($acte);
            }

            $acte
                ->setServiceGrille($service)
                ->setSousCategorie('' === $sousCategorie ? null : $sousCategorie)
                ->setLibelle($libelle)
                ->setTarif($tarifA)
                ->setTarifA0($tarifA0)
                ->setTarifA1($tarifA1)
                ->setTarifB($tarifB)
                ->setTarifC($tarifC)
                ->setUnite(ActeFinancier::UNITE_FC)
                ->setStatut(ActeFinancier::STATUT_ACTIF);

            if ($isNew) {
                ++$imported;
            } else {
                ++$updated;
            }
        }

        $this->entityManager->flush();
        $spreadsheet->disconnectWorksheets();

        return [
            'imported' => $imported,
            'updated' => $updated,
            'skipped' => $skipped,
            'total' => $imported + $updated,
        ];
    }

    private function assertHeader(Worksheet $sheet): void
    {
        $serviceHeader = mb_strtolower($this->cellString($sheet, self::HEADER_ROW, 2));
        $acteHeader = mb_strtolower($this->cellString($sheet, self::HEADER_ROW, 4));
        if (!str_contains($serviceHeader, 'service') || !str_contains($acteHeader, 'acte')) {
            throw new ConflictException('En-têtes de la feuille Grille CHHU inattendus. Vérifiez le fichier consolidé.');
        }
    }

    private function buildCode(string $service, string $libelle): string
    {
        $prefix = $this->tarificationService->prefixForService($service);
        $hash = strtoupper(substr(sha1($service . '|' . $libelle), 0, 8));
        $code = $prefix . '-' . $hash;

        if (null === $this->acteFinancierRepository->findOneBy(['code' => $code])) {
            return $code;
        }

        return $prefix . '-' . strtoupper(substr(sha1($service . '|' . $libelle . '|2'), 0, 8));
    }

    private function cellString(Worksheet $sheet, int $row, int $col): string
    {
        $value = $sheet->getCell([$col, $row])->getValue();
        if (null === $value) {
            return '';
        }

        return trim((string) $value);
    }

    private function cellMoney(Worksheet $sheet, int $row, int $col): string
    {
        $value = $sheet->getCell([$col, $row])->getCalculatedValue();
        if (null === $value || '' === $value) {
            return '0.00';
        }
        if (!is_numeric($value)) {
            $normalized = str_replace([' ', ','], ['', '.'], (string) $value);
            if (!is_numeric($normalized)) {
                return '0.00';
            }
            $value = $normalized;
        }

        return number_format((float) $value, 2, '.', '');
    }
}
