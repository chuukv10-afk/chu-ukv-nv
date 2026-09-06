<?php

namespace App\Service\Export;

use App\Entity\Personnel;
use PhpOffice\PhpSpreadsheet\RichText\RichText;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Response;

/**
 * Export générique PDF / Excel avec la charte CHU UKV.
 */
final class TableExportService
{
    public function __construct(
        private readonly PdfExportService $pdfExportService,
        private readonly Security $security,
    ) {
    }

    /**
     * @param list<string>              $headers
     * @param list<list<string|null>>   $dataRows lignes sans la colonne N°
     * @param list<int>                 $htmlColumnIndexes index dans $headers (incluant N°)
     * @param list<int>                 $richTextColumnIndexes index dans les valeurs de $dataRows (sans N°)
     */
    public function createResponse(
        string $format,
        array $headers,
        array $dataRows,
        string $reportTitle,
        string $filenamePrefix,
        string $emptyMessage,
        array $htmlColumnIndexes = [],
        array $richTextColumnIndexes = [],
        string $pdfOrientation = 'portrait',
    ): Response {
        return match ($format) {
            'pdf' => $this->createPdfResponse(
                $headers,
                $dataRows,
                $reportTitle,
                $filenamePrefix,
                $emptyMessage,
                $htmlColumnIndexes,
                $pdfOrientation,
            ),
            'xlsx' => $this->createExcelResponse(
                $headers,
                $dataRows,
                $reportTitle,
                $filenamePrefix,
                $richTextColumnIndexes,
            ),
            default => throw new \InvalidArgumentException('Format d\'export invalide.'),
        };
    }

    /**
     * @param list<string>            $headers
     * @param list<list<string|null>> $dataRows
     * @param list<int>               $htmlColumnIndexes
     */
    public function createPdfResponse(
        array $headers,
        array $dataRows,
        string $reportTitle,
        string $filenamePrefix,
        string $emptyMessage,
        array $htmlColumnIndexes = [],
        string $orientation = 'portrait',
    ): Response {
        $tableRows = [];
        foreach ($dataRows as $index => $row) {
            $tableRows[] = array_merge(
                [(string) ($index + 1)],
                array_values($row),
            );
        }

        return $this->pdfExportService->createTableDocumentResponse(
            reportTitle: mb_strtoupper($reportTitle, 'UTF-8'),
            tableHtml: $this->pdfExportService->buildTableHtml(
                $headers,
                $tableRows,
                $emptyMessage,
                $htmlColumnIndexes,
            ),
            filename: $this->buildFilename($filenamePrefix, 'pdf'),
            generatedBy: $this->resolveCurrentUserDisplayName(),
            generatedAt: new \DateTimeImmutable(),
            orientation: $orientation,
        );
    }

    /**
     * @param list<string>            $headers
     * @param list<list<string|null>> $dataRows
     * @param list<int>               $richTextColumnIndexes
     */
    public function createExcelResponse(
        array $headers,
        array $dataRows,
        string $reportTitle,
        string $filenamePrefix,
        array $richTextColumnIndexes = [],
    ): Response {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle(substr($filenamePrefix, 0, 31));

        $columnCount = count($headers);
        $reportTitleUpper = mb_strtoupper($reportTitle, 'UTF-8');
        $sheet->mergeCells([1, 1, $columnCount, 1]);
        $sheet->setCellValue([1, 1], $reportTitleUpper);
        $sheet->getStyle([1, 1])->getFont()->setBold(true);
        $sheet->getStyle([1, 1])->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        foreach ($headers as $columnIndex => $header) {
            $sheet->getCell([$columnIndex + 1, 2])->setValue($header);
        }

        $headerRange = 'A2:' . $sheet->getCell([$columnCount, 2])->getCoordinate();
        $sheet->getStyle($headerRange)->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => '1E5AA8'],
            ],
        ]);

        $rowIndex = 3;
        foreach ($dataRows as $index => $row) {
            $sheet->setCellValue([1, $rowIndex], $index + 1);
            foreach (array_values($row) as $columnIndex => $value) {
                $cell = [$columnIndex + 2, $rowIndex];
                if (\in_array($columnIndex, $richTextColumnIndexes, true) && \is_string($value) && '' !== $value) {
                    $sheet->setCellValue($cell, $this->formatBoldPrefixRichText($value));
                    continue;
                }

                $sheet->setCellValue($cell, $value ?? '');
            }
            ++$rowIndex;
        }

        foreach (range(1, $columnCount) as $columnIndex) {
            $sheet->getColumnDimensionByColumn($columnIndex)->setAutoSize(true);
        }

        $writer = new Xlsx($spreadsheet);
        $tempFile = tempnam(sys_get_temp_dir(), 'chu_export_');
        if (false === $tempFile) {
            throw new \RuntimeException('Impossible de préparer le fichier Excel.');
        }

        $writer->save($tempFile);
        $content = file_get_contents($tempFile);
        unlink($tempFile);

        if (false === $content) {
            throw new \RuntimeException('Impossible de lire le fichier Excel généré.');
        }

        return new Response(
            $content,
            Response::HTTP_OK,
            [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'Content-Disposition' => 'attachment; filename="' . $this->buildFilename($filenamePrefix, 'xlsx') . '"',
            ],
        );
    }

    public function formatBoldPrefixHtml(?string $value): ?string
    {
        if (null === $value || '' === trim($value)) {
            return null;
        }

        $parts = [];
        foreach (explode('; ', $value) as $part) {
            if (preg_match('/^(.+?) : (.+)$/', $part, $matches)) {
                $parts[] = sprintf(
                    '<strong>%s</strong> : %s',
                    htmlspecialchars($matches[1], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),
                    htmlspecialchars($matches[2], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),
                );
                continue;
            }

            $parts[] = htmlspecialchars($part, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        }

        return implode('; ', $parts);
    }

    public function formatBoldPrefixRichText(string $value): RichText
    {
        $richText = new RichText();
        $segments = explode('; ', $value);

        foreach ($segments as $index => $part) {
            if ($index > 0) {
                $richText->createTextRun('; ');
            }

            if (preg_match('/^(.+?) : (.+)$/', $part, $matches)) {
                $bold = $richText->createTextRun($matches[1]);
                $bold->getFont()->setBold(true);
                $richText->createTextRun(' : ' . $matches[2]);
                continue;
            }

            $richText->createTextRun($part);
        }

        return $richText;
    }

    private function buildFilename(string $prefix, string $extension): string
    {
        return sprintf('%s_%s.%s', $prefix, (new \DateTimeImmutable())->format('Ymd_His'), $extension);
    }

    public function resolveCurrentUserDisplayName(): string
    {
        $user = $this->security->getUser();
        if (!$user instanceof Personnel) {
            return '';
        }

        $parts = array_filter([
            $user->getPrenom(),
            $user->getNom(),
            $user->getPostNom(),
        ], static fn (?string $part): bool => null !== $part && '' !== trim($part));

        $fullName = trim(implode(' ', $parts));

        return '' !== $fullName ? $fullName : (string) ($user->getMatricule() ?? '');
    }
}
