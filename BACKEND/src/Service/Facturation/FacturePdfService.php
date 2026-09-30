<?php

namespace App\Service\Facturation;

use App\Entity\CategorieTarifaire;
use App\Entity\Facture;
use App\Entity\FactureLigne;
use App\Service\Export\ChuPdfLayoutProvider;
use App\Service\Export\PdfExportService;
use App\Util\CalendarDate;
use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\ErrorCorrectionLevel;
use Symfony\Component\HttpFoundation\Response;

/**
 * Facture au modèle motemaHub : en-tête institutionnel, tableau des actes, totaux, signatures.
 * L'heure imprimée est celle de Kinshasa (Africa/Kinshasa).
 */
final class FacturePdfService
{
    public function __construct(
        private readonly ChuPdfLayoutProvider $layoutProvider,
        private readonly PdfExportService $pdfExportService,
    ) {
    }

    public function createResponse(Facture $facture): Response
    {
        $slug = preg_replace('/[^A-Za-z0-9]+/', '-', $facture->getNumero() ?: (string) $facture->getId()) ?: 'facture';

        return $this->pdfExportService->createDownloadResponse(
            $this->renderDocument($facture),
            'facture-' . $slug . '.pdf',
            'portrait',
            inline: true,
        );
    }

    private function renderDocument(Facture $facture): string
    {
        $now = new \DateTimeImmutable('now', new \DateTimeZone(CalendarDate::TIMEZONE));
        $generated = $now->format('d/m/Y') . ' à ' . $now->format('H:i');
        $faitA = $now->format('d/m/Y');

        [$payClass, $payLabel] = $this->paymentBadge($facture);
        $approval = $this->approvalBadge($facture);
        $logo = $this->layoutProvider->getLogoDataUri();
        $qr = $this->qrDataUri($facture->getNumero());

        $logoHtml = '' !== $logo ? '<img src="' . $logo . '" width="70" alt="Logo CHU">' : '';
        $qrHtml = '' !== $qr ? '<img src="' . $qr . '" width="70" alt="QR">' : '';

        $patient = $facture->getPatient();
        $dossier = $patient?->getDpi()?->getNumDossier() ?: ($patient?->getCodeUkv() ?: '—');
        $patientRows = $this->infoRow('Nom', '<strong>' . $this->e($patient?->getFullName() ?? '—') . '</strong>')
            . $this->infoRow('N° dossier', $this->e($dossier));
        if ($patient?->getTelephone()) {
            $patientRows .= $this->infoRow('Téléphone', $this->e($patient->getTelephone()));
        }
        $patientRows .= $this->infoRow('Catégorie', $this->e($this->categorieLabel($facture->getCategorieTarifaire())));
        $structure = $facture->getStructure();
        if (null !== $structure) {
            $label = (string) $structure->getLibelle();
            if ($facture->getNumeroAffiliation()) {
                $label .= ' (' . $facture->getNumeroAffiliation() . ')';
            }
            $patientRows .= $this->infoRow('Structure', $this->e($label));
        }

        $lineRows = '';
        foreach ($facture->getLignes() as $ligne) {
            $service = $ligne->getServiceLibelle() ?: $ligne->getService()?->getLibelle();
            $meta = trim(implode(' · ', array_filter([
                $service,
                $ligne->getCodeActe(),
            ], static fn (?string $part): bool => null !== $part && '' !== trim($part))));
            $metaHtml = '' !== $meta
                ? '<div style="color:#94a3b8;font-size:8px;">' . $this->e($meta) . '</div>'
                : '';
            $lineRows .= '<tr>'
                . '<td>' . $this->e($ligne->getLibelle()) . $metaHtml . '</td>'
                . '<td class="num">' . $ligne->getQuantite() . '</td>'
                . '<td class="num">' . $this->e($this->money($ligne->getTarifUnitaire())) . '</td>'
                . '<td class="num">' . $this->e($this->formatDiscount($ligne)) . '</td>'
                . '<td class="num">' . $this->e($this->money($ligne->getTarifTotal())) . '</td>'
                . '</tr>';
        }
        if ('' === $lineRows) {
            $lineRows = '<tr><td colspan="5" style="text-align:center;color:#94a3b8;">Aucun acte.</td></tr>';
        }

        $hasDiscount = (float) $facture->getRemiseMontant() > 0;
        $hasPaid = (float) $facture->getMontantPaye() > 0;
        $totals = '';
        if ($hasDiscount) {
            $totals .= $this->totalRow('Sous-total', $this->money($facture->getMontantBrut()));
            $totals .= $this->totalRow('Remise', '-' . $this->money($facture->getRemiseMontant()));
        }
        $totals .= $this->totalRow('Total à payer', $this->money($facture->getMontantTotal()), true);
        if ($hasPaid) {
            $totals .= $this->totalRow('Déjà payé', $this->money($facture->getMontantPaye()));
            $totals .= $this->totalRow('Solde', $this->money($facture->resteAPayer()));
        }

        $comptable = $facture->isValidee() ? $this->personName($facture->getUpdatedBy() ?? $facture->getCreatedBy()) : '—';
        $approvedAt = '';
        if ($facture->isValidee() && null !== $facture->getUpdatedAt()) {
            $approved = \DateTimeImmutable::createFromInterface($facture->getUpdatedAt())
                ->setTimezone(new \DateTimeZone(CalendarDate::TIMEZONE));
            $approvedAt = '<div style="color:#94a3b8;font-size:8px;">Approuvée le ' . $this->e($approved->format('d/m/Y H:i')) . '</div>';
        }

        $approvalHtml = '';
        if (null !== $approval) {
            $approvalHtml = '<span class="badge badge-' . $approval[0] . '">' . $this->e($approval[1]) . '</span>';
        }

        return <<<HTML
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Facture — {$this->e($facture->getNumero())}</title>
    <style>
        @page { margin: 180px 35px 110px 35px; }
        body { font-family: DejaVu Sans, sans-serif; color: #2c3e50; margin: 0; padding: 0; font-size: 10px; }
        #header { position: fixed; top: -160px; left: 0; right: 0; height: 140px; border-bottom: 1px solid #333; }
        #footer { position: fixed; bottom: -80px; left: 0; right: 0; height: 70px; border-top: 1px solid #0087cb; padding-top: 10px; }
        .footer-table, .header-table { width: 100%; border: none; }
        .footer-table td, .header-table td { border: none; vertical-align: top; padding: 0; }
        .header-table td { vertical-align: middle; }
        .footer-table { font-size: 8px; color: #555; }
        .republique { font-style: italic; color: red; font-size: 13px; }
        .ministere { font-weight: bold; font-size: 8px; text-transform: uppercase; }
        .universite { font-weight: bold; color: #0087cb; font-size: 11px; }
        .chu { font-weight: bold; color: red; font-size: 13px; text-transform: uppercase; }
        .title-box { text-align: center; margin-bottom: 16px; }
        .title-box h3 { background-color: #f8f9fa; border: 1px solid #dee2e6; padding: 8px 28px; text-transform: uppercase; font-size: 15px; color: #2c3e50; margin: 0 auto; }
        .title-box p { margin-top: 8px; color: #667085; font-size: 10px; }
        .badge { padding: 3px 8px; font-size: 9px; font-weight: bold; text-transform: uppercase; background: #e2e8f0; color: #334155; }
        .badge-REGLEE { background: #dcfce7; color: #166534; }
        .badge-EN_COURS { background: #fef3c7; color: #b45309; }
        .badge-NON_REGLEE { background: #fee2e2; color: #b91c1c; }
        .badge-ANNULEE { background: #f1f5f9; color: #475569; }
        .badge-approved { background: #dbeafe; color: #1d4ed8; }
        .badge-pending { background: #fef3c7; color: #b45309; }
        .section { margin-bottom: 12px; border: 1px solid #e2e8f0; }
        .section-title { background: #f8fafc; padding: 7px 12px; font-weight: bold; color: #475569; text-transform: uppercase; font-size: 9px; }
        .section-body { padding: 10px 12px; }
        table.info { width: 100%; border-collapse: collapse; }
        table.info td { padding: 5px 4px; vertical-align: top; }
        table.info td.label { width: 32%; color: #64748b; font-weight: bold; }
        table.lines { width: 100%; border-collapse: collapse; font-size: 9px; }
        table.lines th { background-color: #0087cb; color: white; text-align: left; padding: 7px 6px; border: 1px solid #0076b0; text-transform: uppercase; font-size: 8px; }
        table.lines td { padding: 7px 6px; border-bottom: 1px solid #e7edf3; border-left: 0.5px solid #edf2f7; border-right: 0.5px solid #edf2f7; }
        table.lines tbody tr:nth-child(even) { background-color: #fcfcfc; }
        .num { text-align: right; }
        table.totals { width: 45%; border-collapse: collapse; margin-left: 55%; font-size: 10px; }
        table.totals td { padding: 5px 8px; }
        table.totals td.label { color: #64748b; }
        table.totals td.value { text-align: right; font-weight: bold; }
        table.totals tr.grand td { border-top: 2px solid #0087cb; font-size: 13px; color: #0087cb; }
        .signatures { margin-top: 26px; }
        .signatures table { width: 100%; border-collapse: collapse; }
        .signatures td { width: 50%; vertical-align: top; padding: 8px; text-align: center; }
        .sign-line { margin-top: 42px; border-top: 1px solid #94a3b8; padding-top: 6px; font-size: 9px; color: #64748b; }
    </style>
</head>
<body>
    <div id="header">
        <table class="header-table">
            <tr>
                <td style="width: 15%;">{$logoHtml}</td>
                <td style="width: 70%; text-align: center;">
                    <div class="republique">République Démocratique du Congo</div>
                    <div class="ministere">Ministère de l'Enseignement Supérieur, Universitaire, Recherche Scientifique et Innovations</div>
                    <div class="universite">Université Président Joseph Kasa-Vubu</div>
                    <div class="chu">Centre Hospitalier Universitaire</div>
                    <div style="font-weight: bold; font-size: 11px;">CHU de Boma - Ville de Boma (Kongo Central)</div>
                </td>
                <td style="width: 15%; text-align: right;">{$qrHtml}</td>
            </tr>
        </table>
    </div>
    <div id="footer">
        <table class="footer-table">
            <tr>
                <td style="width: 35%;">
                    <strong>ADRESSE PHYSIQUE</strong><br>
                    N° 186 Bis, Av. UKV, Cellule Kinsamuna<br>
                    Secteur de Boma Bungu, Boma
                </td>
                <td style="width: 35%; text-align: center;">
                    <strong>CONTACTS &amp; SUPPORT</strong><br>
                    Tél: (+243) 821 944 140 | 89 995 683 6<br>
                    E-mail: contact@chu-ukv.cd
                </td>
                <td style="width: 30%; text-align: right;">
                    <strong>RÉFÉRENCE</strong><br>
                    {$this->e($facture->getNumero())}<br>
                    Page <script type="text/php">echo \$PAGE_NUM . " / " . \$PAGE_COUNT;</script>
                </td>
            </tr>
        </table>
    </div>
    <div class="content">
        <div class="title-box">
            <h3>Facture</h3>
            <p>
                Référence <strong>{$this->e($facture->getNumero())}</strong> — générée le {$this->e($generated)}
                <span class="badge badge-{$payClass}">{$this->e($payLabel)}</span>
                {$approvalHtml}
            </p>
        </div>
        <div class="section">
            <div class="section-title">Patient</div>
            <div class="section-body"><table class="info">{$patientRows}</table></div>
        </div>
        <div class="section">
            <div class="section-title">Détail des actes</div>
            <div class="section-body">
                <table class="lines">
                    <thead>
                        <tr>
                            <th>Acte</th>
                            <th class="num">Qté</th>
                            <th class="num">Prix réel</th>
                            <th class="num">Remise</th>
                            <th class="num">Net</th>
                        </tr>
                    </thead>
                    <tbody>{$lineRows}</tbody>
                </table>
                <table class="totals">{$totals}</table>
            </div>
        </div>
        <div class="signatures">
            <table>
                <tr>
                    <td>
                        <div><strong>Comptable</strong></div>
                        <div>{$this->e($comptable)}</div>
                        {$approvedAt}
                        <div class="sign-line">Signature</div>
                    </td>
                    <td>
                        <div><strong>Caisse</strong></div>
                        <div>&nbsp;</div>
                        <div class="sign-line">Signature &amp; cachet</div>
                    </td>
                </tr>
            </table>
        </div>
        <div style="margin-top: 24px; text-align: right; font-size: 10px;">
            <p style="margin-right: 40px;">Fait à Boma, le {$this->e($faitA)}</p>
        </div>
    </div>
</body>
</html>
HTML;
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function paymentBadge(Facture $facture): array
    {
        if (Facture::STATUT_ANNULEE === $facture->getStatut()) {
            return ['ANNULEE', 'Annulée'];
        }

        return match ($facture->getStatutPaiement()) {
            Facture::PAIEMENT_PAYEE => ['REGLEE', 'Réglée'],
            Facture::PAIEMENT_PARTIELLE => ['EN_COURS', 'Paiement partiel'],
            default => ['NON_REGLEE', 'Non réglée'],
        };
    }

    /**
     * @return array{0: string, 1: string}|null
     */
    private function approvalBadge(Facture $facture): ?array
    {
        if (Facture::STATUT_ANNULEE === $facture->getStatut()) {
            return null;
        }
        if ($facture->isValidee()) {
            return ['approved', 'Approuvée'];
        }

        return ['pending', "En attente d'approbation"];
    }

    private function infoRow(string $label, string $valueHtml): string
    {
        return '<tr><td class="label">' . $this->e($label) . '</td><td>' . $valueHtml . '</td></tr>';
    }

    private function totalRow(string $label, string $amount, bool $grand = false): string
    {
        $class = $grand ? ' class="grand"' : '';

        return '<tr' . $class . '><td class="label">' . $this->e($label) . '</td><td class="value">' . $this->e($amount) . ' FC</td></tr>';
    }

    private function formatDiscount(FactureLigne $ligne): string
    {
        $amount = (float) $ligne->getRemiseMontant();
        if ($amount <= 0) {
            return '—';
        }
        if (Facture::REMISE_POURCENTAGE === $ligne->getRemiseType()) {
            return '-' . $this->money($amount) . ' (' . $this->formatNumber((float) $ligne->getRemiseValeur()) . ' %)';
        }

        return '-' . $this->money($amount);
    }

    private function money(string|float $value): string
    {
        return number_format((float) $value, 2, ',', ' ');
    }

    private function formatNumber(float $value): string
    {
        if (abs($value - round($value)) < 0.0001) {
            return (string) (int) round($value);
        }

        return rtrim(rtrim(number_format($value, 2, ',', ' '), '0'), ',');
    }

    private function categorieLabel(string $code): string
    {
        foreach (CategorieTarifaire::definitions() as $definition) {
            if ($definition['code'] === $code) {
                return $definition['libelle'];
            }
        }

        return $code;
    }

    private function personName(?object $person): string
    {
        if (null === $person || !method_exists($person, 'getNom')) {
            return '—';
        }

        $name = trim(implode(' ', array_filter([
            $person->getNom(),
            method_exists($person, 'getPostNom') ? $person->getPostNom() : null,
            method_exists($person, 'getPrenom') ? $person->getPrenom() : null,
        ])));

        return '' !== $name ? $name : '—';
    }

    private function qrDataUri(string $numero): string
    {
        try {
            return (new Builder(
                data: $numero,
                errorCorrectionLevel: ErrorCorrectionLevel::Medium,
                size: 180,
                margin: 8,
            ))->build()->getDataUri();
        } catch (\Throwable) {
            return '';
        }
    }

    private function e(?string $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
