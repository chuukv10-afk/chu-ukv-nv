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
        foreach ($chunks as $pair) {
            $left = $this->renderCard($pair[0]);
            $right = isset($pair[1]) ? $this->renderCard($pair[1]) : '';
            $rows .= '<tr><td class="cell">' . $left . '</td><td class="cell">' . $right . '</td></tr>';
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
        @page { margin: 10mm; }
        body { font-family: DejaVu Sans, sans-serif; color: #111; margin: 0; }
        table.grid { width: 100%; border-collapse: separate; border-spacing: 8px 10px; }
        td.cell { width: 50%; vertical-align: top; }
        .card {
            border: 1.2px solid #1f2937;
            text-align: center;
            padding: 10px 8px 12px;
        }
        .brand { font-size: 15px; font-weight: 700; letter-spacing: 0.04em; text-transform: uppercase; color: #111; margin-bottom: 8px; }
        .qr { width: 132px; height: 132px; }
        .caption { margin-top: 12px; }
        .code { font-size: 16px; font-weight: 700; line-height: 1.25; letter-spacing: 0.01em; }
        .name { font-size: 15px; font-weight: 700; line-height: 1.25; margin-top: 6px; color: #111; }
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
        $nameHtml = '' !== $safeName ? '<div class="name">' . $safeName . '</div>' : '';

        return <<<HTML
<div class="card">
    <div class="brand">CHU UKV · Intendance</div>
    <img class="qr" src="{$qr}" alt="QR {$safeCode}" />
    <div class="caption">
        <div class="code">{$safeCode}</div>
        {$nameHtml}
    </div>
</div>
HTML;
    }
}
