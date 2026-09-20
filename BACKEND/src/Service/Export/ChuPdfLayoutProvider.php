<?php

namespace App\Service\Export;

use Dompdf\Dompdf;
use Dompdf\Options;

/**
 * Fournit l'en-tête et le pied de page officiels CHU UKV pour les exports PDF.
 * Contenu aligné sur assets/export/chu/enteteCHU.docx.
 */
final class ChuPdfLayoutProvider
{
    public function __construct(
        private readonly string $assetsDir,
    ) {
    }

    public function buildDocument(
        string $reportTitle,
        string $contentHtml,
        string $generatedBy,
        ?\DateTimeInterface $generatedAt = null,
        string $orientation = 'landscape',
        ?string $closingDateLine = null,
        ?string $closingAuthor = null,
        ?string $headerRightHtml = null,
        bool $includeDocumentChrome = true,
        bool $showPageNumbers = true,
    ): string {
        $headerHtml = $this->renderHeader($orientation, $headerRightHtml);
        $footerHtml = $this->renderFooter($orientation, $showPageNumbers);
        $closingHtml = $this->renderClosingHtml($closingDateLine, $closingAuthor);
        $styles = $this->baseStyles($orientation);
        $safeTitle = htmlspecialchars(mb_strtoupper($reportTitle, 'UTF-8'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $chromeHeader = $includeDocumentChrome
            ? '<header class="chu-header">' . $headerHtml . '</header>'
            : '';
        $titleHtml = $includeDocumentChrome && '' !== trim($reportTitle)
            ? '<h1 class="report-title">' . $safeTitle . '</h1>'
            : '';

        return <<<HTML
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <style>{$styles}</style>
</head>
<body>
    {$chromeHeader}
    <footer class="chu-footer">{$footerHtml}</footer>

    <main class="chu-content">
        {$titleHtml}
        {$contentHtml}
        {$closingHtml}
    </main>
</body>
</html>
HTML;
    }

    private function renderClosingHtml(?string $dateLine, ?string $author): string
    {
        if (null === $dateLine) {
            return '';
        }

        $safeDate = htmlspecialchars($dateLine, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $safeAuthor = htmlspecialchars(trim($author ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $authorHtml = '' !== $safeAuthor
            ? '<div class="chu-closing-author">' . $safeAuthor . '</div>'
            : '';

        return <<<HTML
<div class="chu-closing">
    <div class="chu-closing-inner">
        <div class="chu-closing-date">{$safeDate}</div>
        {$authorHtml}
    </div>
</div>
HTML;
    }

    public function formatOfficialDateLine(?\DateTimeInterface $generatedAt = null): string
    {
        $generatedAt ??= new \DateTimeImmutable();

        return sprintf(
            'Fait à Boma, le %s à %s',
            $generatedAt->format('d/m/Y'),
            $generatedAt->format('H:i'),
        );
    }

    public function configureDompdfOptions(Options $options): void
    {
        $options->set('chroot', $this->assetsDir);
    }

    public function registerFonts(Dompdf $dompdf): void
    {
        $fontPath = $this->assetsDir . DIRECTORY_SEPARATOR . 'Forte.ttf';
        if (!is_file($fontPath)) {
            return;
        }

        $realPath = realpath($fontPath);
        if (false === $realPath) {
            return;
        }

        $dompdf->getFontMetrics()->registerFont(
            [
                'family' => 'Forte',
                'style' => 'normal',
                'weight' => 'normal',
            ],
            'file:///' . str_replace('\\', '/', $realPath),
        );
    }

    public function renderHeader(string $orientation = 'landscape', ?string $headerRightHtml = null): string
    {
        $logo = $this->getLogoDataUri();
        $hasRight = null !== $headerRightHtml && '' !== trim($headerRightHtml);
        $aside = $hasRight
            ? '<div class="chu-header-right">' . $headerRightHtml . '</div>'
            : '';

        return <<<HTML
<div class="chu-header-inner">
    {$aside}
    <div class="chu-header-line chu-header-line-main">RÉPUBLIQUE DÉMOCRATIQUE DU CONGO</div>
    <div class="chu-header-line chu-header-line-ministry">MINISTÈRE DE L'ENSEIGNEMENT SUPÉRIEUR, UNIVERSITAIRE, RECHERCHE SCIENTIFIQUE ET INNOVATIONS</div>
    <div class="chu-header-line chu-header-line-university">UNIVERSITE PRESIDENT JOSEPH KASA-VUBU</div>
    <div class="chu-header-logo-wrap">
        <img src="{$logo}" alt="Logo CHU UKV" class="chu-header-logo">
    </div>
    <div class="chu-header-line chu-header-line-hospital" style="font-family: forte, 'DejaVu Sans', sans-serif;">Centre Hospitalier Universitaire UKV/BOMA</div>
    <div class="chu-header-separator"></div>
</div>
HTML;
    }

    private function renderFooter(string $orientation = 'landscape', bool $showPageNumbers = true): string
    {
        $rightMargin = 20;
        $fromBottom = 'landscape' === $orientation ? 24 : 28;
        $pageScript = $showPageNumbers ? <<<HTML
<script type="text/php">
if (isset(\$pdf)) {
    \$font = \$fontMetrics->getFont('DejaVu Sans');
    \$size = 8;
    \$text = 'Page {PAGE_NUM} / {PAGE_COUNT}';
    \$textWidth = \$fontMetrics->getTextWidth('Page 000 / 000', \$font, \$size);
    \$x = \$pdf->get_width() - \$textWidth - {$rightMargin};
    \$y = \$pdf->get_height() - {$fromBottom};
    \$pdf->page_text(\$x, \$y, \$text, \$font, \$size, [0.25, 0.25, 0.25]);
}
</script>
HTML : '';

        return <<<HTML
<div class="chu-footer-inner">
    <div class="chu-footer-line">
        N° 186 Bis, Av. UKV, Cellule Kinsamuna, Secteur de Boma Bungu, Territoire de Muanda,
        Kongo Central, République Démocratique du Congo; B.P 314;
    </div>
    <div class="chu-footer-line">
        (+243) 821 944 140 – 89 995 683 6; E-mail: <strong>contact@chu-ukv.cd</strong>; Web: <strong>www.chu-ukv.cd</strong>
    </div>
</div>
{$pageScript}
HTML;
    }

    private function baseStyles(string $orientation): string
    {
        $pageSize = 'landscape' === $orientation ? 'A4 landscape' : 'A4 portrait';
        $footerBottom = 'landscape' === $orientation ? '50px' : '54px';

        return <<<CSS
@font-face {
    font-family: forte;
    font-style: normal;
    font-weight: normal;
    src: url('Forte.ttf') format('truetype');
}

@page {
    size: {$pageSize};
    margin: 16px 20px {$footerBottom} 20px;
}

body {
    font-family: DejaVu Sans, sans-serif;
    font-size: 10px;
    color: #1a1a1a;
    margin: 0;
}

.chu-header {
    position: relative;
    margin: 0 0 4px 0;
    page-break-inside: avoid;
    page-break-after: avoid;
}

.chu-header-inner {
    text-align: center;
    position: relative;
}

.chu-header-right {
    position: absolute;
    right: 0;
    top: 0;
    width: 78px;
    text-align: center;
    z-index: 2;
}

.chu-header-right img {
    width: 72px;
    height: 72px;
    display: block;
    margin: 0 auto;
}

.chu-header-right .cap-qr-caption {
    font-size: 6.5px;
    color: #444;
    line-height: 1.1;
    margin-top: 1px;
}

.chu-header-line {
    line-height: 1.15;
}

.chu-header-line-main {
    font-weight: bold;
    font-size: 13px;
}

.chu-header-line-ministry {
    font-weight: bold;
    font-size: 7.5px;
    margin-top: 2px;
    color: #222;
}

.chu-header-logo-wrap {
    margin: 4px 0 2px;
}

.chu-header-logo {
    height: 48px;
    width: auto;
}

.chu-header-line-university {
    font-weight: bold;
    font-size: 10px;
}

.chu-header-line-hospital {
    font-family: forte, 'DejaVu Sans', sans-serif;
    font-size: 12px;
    margin-top: 1px;
}

.chu-header-separator {
    border-top: 1.5px solid #111;
    margin: 6px auto 0;
    width: 88%;
}

.chu-footer {
    position: fixed;
    bottom: -36px;
    left: 0;
    right: 72px;
    height: 34px;
}

.chu-footer-inner {
    text-align: center;
    font-size: 8px;
    color: #333;
    line-height: 1.2;
}

.chu-footer-line + .chu-footer-line {
    margin-top: 2px;
}

.chu-content {
    width: 100%;
}

.report-title {
    font-size: 15px;
    font-weight: bold;
    text-transform: uppercase;
    text-align: center;
    margin: 10px 0 14px;
    color: #1E5AA8;
}

.chu-table {
    width: 100%;
    border-collapse: collapse;
}

.chu-table thead {
    display: table-header-group;
}

.chu-table tbody tr {
    page-break-inside: auto;
}

.chu-table th,
.chu-table td {
    border: 1px solid #d0d7de;
    padding: 3px 4px;
    font-size: 8px;
    text-align: left;
    vertical-align: top;
}

.chu-table th {
    background: #1E5AA8;
    color: #fff;
    font-weight: bold;
}

.chu-table tr:nth-child(even) td {
    background: #f6f8fa;
}

.chu-table th:first-child,
.chu-table td:first-child {
    text-align: center;
    width: 28px;
}

.chu-empty-row td {
    text-align: center;
    color: #666;
}

.chu-closing {
    margin-top: 14px;
    text-align: right;
    page-break-inside: avoid;
    page-break-before: auto;
}

.chu-table tr.chu-total-row td {
    font-weight: bold;
    background: #e8eef5;
}

.chu-closing-inner {
    display: inline-block;
    text-align: center;
}

.chu-closing-date {
    font-size: 9px;
    color: #262626;
    text-align: right;
}

.chu-closing-author {
    font-size: 10px;
    font-weight: bold;
    color: #1a1a1a;
    margin-top: 4px;
}
CSS;
    }

    private function getLogoDataUri(): string
    {
        $logoPath = $this->assetsDir . DIRECTORY_SEPARATOR . 'logo.jfif';
        if (!is_file($logoPath)) {
            return '';
        }

        $content = file_get_contents($logoPath);
        if (false === $content) {
            return '';
        }

        return 'data:image/jpeg;base64,' . base64_encode($content);
    }
}
