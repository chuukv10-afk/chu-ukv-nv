<?php

namespace App\Service\Export;

use Dompdf\Dompdf;
use Dompdf\Options;
use Symfony\Component\HttpFoundation\Response;

/**
 * Service générique de génération PDF avec la charte CHU UKV.
 */
final class PdfExportService
{
    public function __construct(
        private readonly ChuPdfLayoutProvider $layoutProvider,
    ) {
    }

    public function createTableDocumentResponse(
        string $reportTitle,
        string $tableHtml,
        string $filename,
        string $generatedBy,
        ?\DateTimeInterface $generatedAt = null,
        string $orientation = 'landscape',
    ): Response {
        $generatedAt ??= new \DateTimeImmutable();

        $html = $this->layoutProvider->buildDocument(
            $reportTitle,
            $tableHtml,
            $generatedBy,
            $generatedAt,
            $orientation,
            $this->layoutProvider->formatOfficialDateLine($generatedAt),
            $generatedBy,
        );

        return $this->createDownloadResponse(
            $html,
            $filename,
            $orientation,
            inline: true,
        );
    }

    public function createDownloadResponse(
        string $html,
        string $filename,
        string $orientation = 'landscape',
        bool $inline = false,
    ): Response {
        $options = new Options();
        $options->set('isRemoteEnabled', false);
        $options->set('isPhpEnabled', true);
        $options->set('defaultFont', 'DejaVu Sans');
        $options->set('isFontSubsettingEnabled', true);
        $options->set('dpi', 96);
        $this->layoutProvider->configureDompdfOptions($options);

        $previousLimit = ini_get('memory_limit');
        if (false !== $previousLimit && $this->memoryLimitBytes($previousLimit) < 512 * 1024 * 1024) {
            ini_set('memory_limit', '512M');
        }

        try {
            $dompdf = new Dompdf($options);
            $this->layoutProvider->registerFonts($dompdf);
            $dompdf->loadHtml($html);
            $dompdf->setPaper('A4', $orientation);
            $dompdf->render();
            $binary = $dompdf->output();
            unset($dompdf);

            return new Response(
                $binary,
                Response::HTTP_OK,
                [
                    'Content-Type' => 'application/pdf',
                    'Content-Disposition' => ($inline ? 'inline' : 'attachment') . '; filename="' . $filename . '"',
                ],
            );
        } finally {
            if (is_string($previousLimit) && '' !== $previousLimit) {
                ini_set('memory_limit', $previousLimit);
            }
        }
    }

    /**
     * @param list<string> $headers
     * @param list<list<string|null>> $rows
     * @param list<int> $htmlColumnIndexes
     * @param list<list<string|null>> $summaryRows
     */
    public function buildTableHtml(
        array $headers,
        array $rows,
        string $emptyMessage,
        array $htmlColumnIndexes = [],
        array $summaryRows = [],
    ): string {
        $headerCells = '';

        foreach ($headers as $header) {
            $headerCells .= '<th>' . htmlspecialchars($header, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</th>';
        }

        $bodyRows = '';

        foreach ($rows as $row) {
            $bodyRows .= '<tr>';

            foreach ($row as $columnIndex => $value) {
                $cellValue = (string) ($value ?? '—');
                if (\in_array($columnIndex, $htmlColumnIndexes, true)) {
                    $bodyRows .= '<td>' . $cellValue . '</td>';
                } else {
                    $bodyRows .= '<td>' . htmlspecialchars($cellValue, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</td>';
                }
            }

            $bodyRows .= '</tr>';
        }

        foreach ($summaryRows as $row) {
            $bodyRows .= '<tr class="chu-total-row">';

            foreach ($row as $columnIndex => $value) {
                $cellValue = (string) ($value ?? '');
                if (\in_array($columnIndex, $htmlColumnIndexes, true)) {
                    $bodyRows .= '<td>' . $cellValue . '</td>';
                } else {
                    $bodyRows .= '<td>' . htmlspecialchars($cellValue, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</td>';
                }
            }

            $bodyRows .= '</tr>';
        }

        if ('' === $bodyRows) {
            $colspan = max(1, count($headers));
            $safeEmpty = htmlspecialchars($emptyMessage, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            $bodyRows = '<tr class="chu-empty-row"><td colspan="' . $colspan . '">' . $safeEmpty . '</td></tr>';
        }

        return <<<HTML
<table class="chu-table">
    <thead><tr>{$headerCells}</tr></thead>
    <tbody>{$bodyRows}</tbody>
</table>
HTML;
    }

    private function memoryLimitBytes(string $limit): int
    {
        $normalized = strtoupper(trim($limit));
        if ('-1' === $normalized) {
            return PHP_INT_MAX;
        }

        $factor = 1;
        $unit = substr($normalized, -1);
        $value = $normalized;
        if ('G' === $unit) {
            $factor = 1024 * 1024 * 1024;
            $value = substr($normalized, 0, -1);
        } elseif ('M' === $unit) {
            $factor = 1024 * 1024;
            $value = substr($normalized, 0, -1);
        } elseif ('K' === $unit) {
            $factor = 1024;
            $value = substr($normalized, 0, -1);
        }

        return (int) $value * $factor;
    }
}
