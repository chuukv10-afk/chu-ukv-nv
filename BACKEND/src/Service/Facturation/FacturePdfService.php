<?php

namespace App\Service\Facturation;

use App\Entity\CategorieTarifaire;
use App\Entity\Facture;
use App\Entity\FactureReglement;
use App\Service\Export\ChuPdfLayoutProvider;
use App\Service\Export\PdfExportService;
use Symfony\Component\HttpFoundation\Response;

final class FacturePdfService
{
    private const STATUT_LABELS = [
        Facture::STATUT_BROUILLON => 'Brouillon',
        Facture::STATUT_VALIDEE => 'Approuvée',
        Facture::STATUT_ANNULEE => 'Annulée',
    ];

    private const PAIEMENT_LABELS = [
        Facture::PAIEMENT_NON_PAYEE => 'Non payée',
        Facture::PAIEMENT_PARTIELLE => 'Partiellement payée',
        Facture::PAIEMENT_PAYEE => 'Payée',
    ];

    private const MODE_LABELS = [
        FactureReglement::MODE_ESPECES => 'Espèces',
        FactureReglement::MODE_MOBILE => 'Mobile money',
        FactureReglement::MODE_BANQUE => 'Banque',
        FactureReglement::MODE_CHEQUE => 'Chèque',
    ];

    public function __construct(
        private readonly ChuPdfLayoutProvider $layoutProvider,
        private readonly PdfExportService $pdfExportService,
    ) {
    }

    public function createResponse(Facture $facture): Response
    {
        $html = $this->layoutProvider->buildDocument(
            'Facture',
            $this->renderBody($facture),
            $this->authorName($facture),
            $facture->getDateFacture() ?? $facture->getCreatedAt(),
            'portrait',
            $this->layoutProvider->formatOfficialDateLine($facture->getDateFacture() ?? $facture->getCreatedAt()),
            $this->authorName($facture),
        );

        $slug = preg_replace('/[^A-Za-z0-9]+/', '-', $facture->getNumero() ?: (string) $facture->getId()) ?: 'facture';

        return $this->pdfExportService->createDownloadResponse(
            $html,
            'facture-' . $slug . '.pdf',
            'portrait',
            inline: true,
        );
    }

    private function renderBody(Facture $facture): string
    {
        $patient = $facture->getPatient();
        $structure = $facture->getStructure();
        $categorie = $this->categorieLabel($facture->getCategorieTarifaire());
        $watermark = $facture->isBrouillon()
            ? '<div style="text-align:center;margin:8px 0 12px;padding:6px;border:2px solid #c0392b;color:#c0392b;font-weight:bold;letter-spacing:2px;">BROUILLON — DOCUMENT NON APPROUVÉ</div>'
            : '';

        $rows = '';
        $index = 0;
        foreach ($facture->getLignes() as $ligne) {
            ++$index;
            $remise = (float) $ligne->getRemiseMontant() > 0
                ? $this->e($this->fc($ligne->getRemiseMontant()))
                : '—';
            $rows .= sprintf(
                '<tr><td>%d</td><td>%s</td><td>%s</td><td>%s</td><td style="text-align:center;">%d</td><td style="text-align:right;">%s</td><td style="text-align:right;">%s</td><td style="text-align:right;">%s</td></tr>',
                $index,
                $this->e($ligne->getCodeActe()),
                $this->e($ligne->getLibelle()),
                $this->e($ligne->getServiceLibelle() ?: $ligne->getService()?->getLibelle() ?: '—'),
                $ligne->getQuantite(),
                $this->e($this->fc($ligne->getTarifUnitaire())),
                $remise,
                $this->e($this->fc($ligne->getTarifTotal())),
            );
        }

        if ('' === $rows) {
            $rows = '<tr class="chu-empty-row"><td colspan="8">Aucun acte.</td></tr>';
        }

        $reglementRows = '';
        foreach ($facture->getReglements() as $reglement) {
            $reglementRows .= sprintf(
                '<tr><td>%s</td><td>%s</td><td style="text-align:right;">%s</td><td>%s</td></tr>',
                $this->e($reglement->getDateReglement()?->format('d/m/Y') ?? '—'),
                $this->e(self::MODE_LABELS[$reglement->getMode()] ?? $reglement->getMode()),
                $this->e($this->fc($reglement->getMontant())),
                $this->e($reglement->getNotes() ?: '—'),
            );
        }

        $reglementsHtml = '' !== $reglementRows
            ? '<h2 class="cap-section">Règlements</h2>
            <table class="chu-table">
                <thead><tr><th>Date</th><th>Mode</th><th>Montant</th><th>Notes</th></tr></thead>
                <tbody>' . $reglementRows . '</tbody>
            </table>'
            : '';

        $notesHtml = $facture->getNotes()
            ? '<p><strong>Notes :</strong> ' . nl2br($this->e($facture->getNotes())) . '</p>'
            : '';

        return sprintf(
            '%s
            <div class="cap-meta">
                <span>N° <strong>%s</strong></span>
                <span>Date : <strong>%s</strong></span>
                <span>Statut : <strong>%s</strong></span>
                <span>Paiement : <strong>%s</strong></span>
            </div>
            <h2 class="cap-section">Patient</h2>
            <p><strong>Nom :</strong> %s</p>
            <p><strong>Téléphone :</strong> %s &nbsp; <strong>DPI :</strong> %s &nbsp; <strong>UKV :</strong> %s</p>
            <p><strong>Catégorie :</strong> %s%s</p>
            <h2 class="cap-section">Actes facturés</h2>
            <table class="chu-table">
                <thead>
                    <tr>
                        <th>N°</th>
                        <th>Code</th>
                        <th>Acte</th>
                        <th>Service facturant</th>
                        <th>Qté</th>
                        <th>P.U.</th>
                        <th>Remise</th>
                        <th>Total</th>
                    </tr>
                </thead>
                <tbody>%s</tbody>
            </table>
            <table class="chu-table" style="width:48%%;margin-left:auto;margin-top:10px;">
                <tbody>
                    <tr><td>Montant brut</td><td style="text-align:right;">%s</td></tr>
                    <tr><td>Remise</td><td style="text-align:right;">%s</td></tr>
                    <tr class="chu-total-row"><td>Net à payer</td><td style="text-align:right;">%s</td></tr>
                    <tr><td>Montant payé</td><td style="text-align:right;">%s</td></tr>
                    <tr class="chu-total-row"><td>Reste à payer</td><td style="text-align:right;">%s</td></tr>
                </tbody>
            </table>
            %s
            %s',
            $watermark,
            $this->e($facture->getNumero()),
            $this->e($facture->getDateFacture()?->format('d/m/Y') ?? '—'),
            $this->e(self::STATUT_LABELS[$facture->getStatut()] ?? $facture->getStatut()),
            $this->e(self::PAIEMENT_LABELS[$facture->getStatutPaiement()] ?? $facture->getStatutPaiement()),
            $this->e($patient?->getFullName() ?? '—'),
            $this->e($patient?->getTelephone() ?: '—'),
            $this->e($patient?->getDpi()?->getNumDossier() ?: '—'),
            $this->e($patient?->getCodeUkv() ?: '—'),
            $this->e($categorie),
            $structure
                ? sprintf(
                    ' &nbsp; <strong>Structure :</strong> %s%s',
                    $this->e((string) $structure->getLibelle()),
                    $facture->getNumeroAffiliation() ? ' (' . $this->e($facture->getNumeroAffiliation()) . ')' : '',
                )
                : '',
            $rows,
            $this->e($this->fc($facture->getMontantBrut())),
            $this->e($this->fc($facture->getRemiseMontant())),
            $this->e($this->fc($facture->getMontantTotal())),
            $this->e($this->fc($facture->getMontantPaye())),
            $this->e($this->fc($facture->resteAPayer())),
            $reglementsHtml,
            $notesHtml,
        );
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

    private function authorName(Facture $facture): string
    {
        $author = $facture->getCreatedBy();
        if (null === $author) {
            return '';
        }

        return trim(implode(' ', array_filter([
            $author->getNom(),
            $author->getPostNom(),
            $author->getPrenom(),
        ])));
    }

    private function fc(string $value): string
    {
        return number_format((float) $value, 2, ',', ' ') . ' FC';
    }

    private function e(?string $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
