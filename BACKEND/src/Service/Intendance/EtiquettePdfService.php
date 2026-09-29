<?php

namespace App\Service\Intendance;

use App\Entity\BienPatrimonial;
use App\Service\Export\PdfExportService;
use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\ErrorCorrectionLevel;
use Symfony\Component\HttpFoundation\Response;

final class EtiquettePdfService
{
    public function __construct(
        private readonly PdfExportService $pdfExportService,
        private readonly string $publicFrontendUrl,
    ) {
    }

    /**
     * @param list<BienPatrimonial> $biens
     */
    public function createResponse(array $biens): Response
    {
        $rows = '';
        $chunks = array_chunk($biens, 2);
        foreach ($chunks as $index => $pair) {
            $left = $this->renderCard($pair[0]);
            $right = isset($pair[1]) ? $this->renderCard($pair[1]) : '';
            $break = 0 !== $index && 0 === $index % 5 ? ' break' : '';
            $rows .= '<tr class="row' . $break . '"><td class="cell">' . $left . '</td><td class="cell">' . $right . '</td></tr>';
        }

        if ('' === $rows) {
            $rows = '<tr><td class="empty" colspan="2">Aucun bien sélectionné.</td></tr>';
        }

        $html = <<<HTML
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <style>
        @page { margin: 6mm 8mm; }
        body { font-family: DejaVu Sans, sans-serif; color: #111; margin: 0; }
        table.grid { width: 100%; border-collapse: separate; border-spacing: 5px 3px; }
        tr.row { page-break-inside: avoid; }
        tr.break { page-break-before: always; }
        td.cell { width: 50%; vertical-align: top; }
        .card {
            border: 1px solid #1f2937;
            text-align: center;
            padding: 4px 6px 5px;
            page-break-inside: avoid;
        }
        .brand { font-size: 12px; font-weight: 700; letter-spacing: 0.03em; text-transform: uppercase; color: #111; margin: 0 0 2px; }
        .qr { width: 96px; height: 96px; }
        .caption { margin-top: 3px; }
        .code { font-size: 13px; font-weight: 700; line-height: 1.15; }
        .name { font-size: 12px; font-weight: 700; line-height: 1.15; margin-top: 2px; color: #111; }
        .local { font-size: 11px; font-weight: 700; line-height: 1.15; margin-top: 1px; color: #111; }
        .empty { text-align: center; padding: 40px; color: #6b7280; }
    </style>
</head>
<body>
    <table class="grid">{$rows}</table>
</body>
</html>
HTML;

        return $this->pdfExportService->createDownloadResponse(
            $html,
            'etiquettes-parc-' . (new \DateTimeImmutable())->format('Y-m-d') . '.pdf',
            'portrait',
            inline: true,
        );
    }

    private function verificationUrl(string $code): string
    {
        $base = rtrim($this->publicFrontendUrl, '/');

        return $base . '/verification/bien?code=' . rawurlencode($code);
    }

    private function renderCard(BienPatrimonial $bien): string
    {
        $code = (string) $bien->getCodeInventaire();
        $qr = (new Builder(
            data: $this->verificationUrl($code),
            errorCorrectionLevel: ErrorCorrectionLevel::Medium,
            size: 240,
            margin: 6,
        ))->build()->getDataUri();

        $safeCode = htmlspecialchars($code, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $safeName = htmlspecialchars((string) ($bien->getType()?->getLibelle() ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $safeLocal = htmlspecialchars((string) ($bien->getLocal()?->getLibelle() ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $nameHtml = '' !== $safeName ? '<div class="name">' . $safeName . '</div>' : '';
        $localHtml = '' !== $safeLocal ? '<div class="local">' . $safeLocal . '</div>' : '';

        return <<<HTML
<div class="card">
    <div class="brand">CHU UKV · Intendance</div>
    <img class="qr" src="{$qr}" alt="QR {$safeCode}" />
    <div class="caption">
        <div class="code">{$safeCode}</div>
        {$nameHtml}
        {$localHtml}
    </div>
</div>
HTML;
    }
}
