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
    ): string {
        $headerHtml = $this->renderHeader($orientation);
        $footerHtml = $this->renderFooter($orientation);
        $closingHtml = $this->renderClosingHtml($closingDateLine, $closingAuthor);
        $styles = $this->baseStyles($orientation);
        $safeTitle = htmlspecialchars(mb_strtoupper($reportTitle, 'UTF-8'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

        return <<<HTML
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <style>{$styles}</style>
</head>
<body>
    <header class="chu-header">{$headerHtml}</header>
    <footer class="chu-footer">{$footerHtml}</footer>

    <main class="chu-content">
        <h1 class="report-title">{$safeTitle}</h1>
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

    private function renderHeader(string $orientation = 'landscape'): string
    {
        $logo = $this->getLogoDataUri();

        return <<<HTML
<div class="chu-header-inner">
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

    private function renderFooter(string $orientation = 'landscape'): string
    {
        $pageNumberX = 'landscape' === $orientation ? 395 : 280;

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
<script type="text/php">
if (isset(\$pdf)) {
    \$font = \$fontMetrics->getFont('DejaVu Sans');
    \$pdf->page_text({$pageNumberX}, 18, 'Page {PAGE_NUM} / {PAGE_COUNT}', \$font, 7, [0.25, 0.25, 0.25]);
}
</script>
HTML;
    }

    private function baseStyles(string $orientation): string
    {
        $pageSize = 'landscape' === $orientation ? 'A4 landscape' : 'A4 portrait';
        $contentTop = 'landscape' === $orientation ? '128px' : '140px';
        $footerBottom = 'landscape' === $orientation ? '78px' : '86px';

        return <<<CSS
@font-face {
    font-family: forte;
    font-style: normal;
    font-weight: normal;
    src: url('Forte.ttf') format('truetype');
}

@page {
    size: {$pageSize};
    margin: {$contentTop} 28px {$footerBottom} 28px;
}

body {
    font-family: DejaVu Sans, sans-serif;
    font-size: 10px;
    color: #1a1a1a;
    margin: 0;
}

.chu-header {
    position: fixed;
    top: -108px;
    left: 0;
    right: 0;
    height: 108px;
}

.chu-header-inner {
    text-align: center;
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
    bottom: -48px;
    left: 0;
    right: 0;
    height: 48px;
}

.chu-footer-inner {
    text-align: center;
    font-size: 9.5px;
    color: #333;
    line-height: 1.25;
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
    margin: 18px 0 16px;
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
    page-break-inside: avoid;
}

.chu-table th,
.chu-table td {
    border: 1px solid #d0d7de;
    padding: 5px 6px;
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
    margin-top: 200px;
    text-align: right;
    page-break-inside: avoid;
    page-break-before: auto;
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
