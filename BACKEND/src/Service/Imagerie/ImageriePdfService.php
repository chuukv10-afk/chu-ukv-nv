<?php

namespace App\Service\Imagerie;

use App\Entity\EtudeImagerie;
use App\Entity\Personnel;
use App\Exception\ConflictException;
use App\Service\Export\ChuPdfLayoutProvider;
use App\Service\Export\PdfExportService;
use App\Service\Personnel\PersonnelSignatureService;
use Symfony\Component\HttpFoundation\Response;

final class ImageriePdfService
{
    public function __construct(
        private readonly ChuPdfLayoutProvider $layoutProvider,
        private readonly PdfExportService $pdfExportService,
        private readonly PersonnelSignatureService $signatureService,
    ) {
    }

    public function createBonDemandeResponse(EtudeImagerie $etude): Response
    {
        if (EtudeImagerie::STATUT_ANNULEE === $etude->getStatut()) {
            throw new ConflictException('Impossible d\'imprimer le bon d\'une étude annulée.');
        }

        $demandeur = $this->personnelName($etude->getDemandePar());
        $html = $this->layoutProvider->buildDocument(
            'Bon de demande d\'imagerie',
            $this->renderBonBody($etude, $demandeur),
            $demandeur,
            $etude->getCreatedAt(),
            'portrait',
            $this->layoutProvider->formatOfficialDateLine($etude->getCreatedAt()),
            $demandeur !== '—' ? $this->doctorLabel($etude->getDemandePar()) : '',
        );

        return $this->pdfExportService->createDownloadResponse(
            $html,
            'bon-demande-imagerie-' . $this->slug($etude) . '.pdf',
            'portrait',
            inline: true,
        );
    }

    public function createResponse(EtudeImagerie $etude): Response
    {
        if (!in_array($etude->getStatut(), [EtudeImagerie::STATUT_INTERPRETE, EtudeImagerie::STATUT_VALIDE], true)) {
            throw new ConflictException('Le compte-rendu n\'est disponible qu\'après interprétation.');
        }

        $validated = EtudeImagerie::STATUT_VALIDE === $etude->getStatut();
        $signer = $validated ? $etude->getValidePar() : $etude->getInterpretePar();
        $signerName = $this->personnelName($signer);
        $date = $validated
            ? ($etude->getValideAt() ?? $etude->getInterpreteAt() ?? $etude->getCreatedAt())
            : ($etude->getInterpreteAt() ?? $etude->getCreatedAt());

        $html = $this->layoutProvider->buildDocument(
            'Compte-rendu d\'imagerie',
            $this->renderCompteRenduBody($etude, $signerName, $validated),
            $signerName,
            $date,
            'portrait',
            $this->layoutProvider->formatOfficialDateLine($date),
            $validated ? $this->doctorLabel($signer) : $signerName,
        );

        return $this->pdfExportService->createDownloadResponse(
            $html,
            'compte-rendu-imagerie-' . $this->slug($etude) . '.pdf',
            'portrait',
            inline: true,
        );
    }

    private function renderBonBody(EtudeImagerie $etude, string $demandeur): string
    {
        $patient = $etude->getPatient();

        return sprintf(
            '<div class="cap-meta"><span>N° <strong>%s</strong></span><span>Date : <strong>%s</strong></span></div>
            <h2 class="cap-section">Patient</h2>
            <p><strong>Nom :</strong> %s</p>
            <h2 class="cap-section">Demande</h2>
            <p><strong>Examen :</strong> %s</p>
            <p><strong>But :</strong> %s</p>
            <p><strong>Renseignements cliniques :</strong> %s</p>
            <p><strong>Médecin demandeur :</strong> %s</p>',
            $this->e((string) $etude->getNumero()),
            $this->e($etude->getCreatedAt()?->format('d/m/Y') ?? '—'),
            $this->e($patient?->getFullName() ?? '—'),
            $this->e($etude->getExamen()?->getLibelle() ?? '—'),
            $this->e($etude->getBut() ?: '—'),
            nl2br($this->e($etude->getIndication() ?: '—')),
            $this->e($this->doctorLabel($etude->getDemandePar()) ?: $demandeur),
        ) . $this->documentStyles();
    }

    private function renderCompteRenduBody(EtudeImagerie $etude, string $signerName, bool $validated): string
    {
        $patient = $etude->getPatient();
        $statut = $validated ? 'Validé' : 'Interprété';
        $signature = $validated ? $this->signatureImage($etude->getValidePar()) : '';
        $signLine = $validated
            ? sprintf('Validé par %s%s.', $this->e($this->doctorLabel($etude->getValidePar()) ?: $signerName), $etude->getValideAt() ? ' le ' . $etude->getValideAt()->format('d/m/Y') : '')
            : sprintf('Interprété par %s%s. En attente de validation.', $this->e($signerName), $etude->getInterpreteAt() ? ' le ' . $etude->getInterpreteAt()->format('d/m/Y') : '');

        return sprintf(
            '<div class="cap-meta"><span>N° <strong>%s</strong></span><span>Statut : <strong>%s</strong></span></div>
            <h2 class="cap-section">Patient</h2>
            <p><strong>Nom :</strong> %s</p>
            <h2 class="cap-section">Examen</h2>
            <p><strong>Acte :</strong> %s</p>
            <p><strong>But :</strong> %s</p>
            <p><strong>Renseignements cliniques :</strong> %s</p>
            <p><strong>Médecin demandeur :</strong> %s</p>
            <p><strong>Technique :</strong> %s</p>
            <h2 class="cap-section">Constatations</h2>
            <p>%s</p>
            <h2 class="cap-section">Conclusion</h2>
            <p><strong>%s</strong></p>
            <div style="margin-top:28px;text-align:right;">
                %s
                <p><em>%s</em></p>
            </div>',
            $this->e((string) $etude->getNumero()),
            $this->e($statut),
            $this->e($patient?->getFullName() ?? '—'),
            $this->e($etude->getExamen()?->getLibelle() ?? '—'),
            $this->e($etude->getBut() ?: '—'),
            nl2br($this->e($etude->getIndication() ?: '—')),
            $this->e($this->doctorLabel($etude->getDemandePar()) ?: '—'),
            $this->e($etude->getTechnique() ?: '—'),
            nl2br($this->e($etude->getConstatations() ?: '—')),
            nl2br($this->e($etude->getConclusion() ?: '—')),
            $signature,
            $signLine,
        ) . $this->documentStyles();
    }

    private function documentStyles(): string
    {
        return <<<'CSS'
<style>
p { margin: 4px 0; }
.cap-meta { display: table; width: 100%; margin-bottom: 8px; font-size: 10px; }
.cap-meta span { display: table-cell; }
.cap-meta span:last-child { text-align: right; }
.cap-section { font-size: 11px; margin: 12px 0 4px; text-transform: uppercase; color: #1E5AA8; }
.cap-signature-img { display: block; max-height: 52px; max-width: 160px; margin: 0 0 6px auto; }
</style>
CSS;
    }

    private function signatureImage(?Personnel $doctor): string
    {
        if (!$doctor instanceof Personnel) {
            return '';
        }
        $dataUri = $this->signatureService->toDataUri($doctor);
        if (null === $dataUri) {
            return '';
        }

        return '<img class="cap-signature-img" src="' . $dataUri . '" alt="Signature" style="display:block;max-height:52px;max-width:160px;margin:0 0 6px auto;" />';
    }

    private function doctorLabel(?Personnel $personnel): string
    {
        $name = $this->personnelName($personnel);
        if ('—' === $name) {
            return '';
        }
        if (str_starts_with(mb_strtolower($name), 'dr')) {
            return $name;
        }

        return 'Dr. ' . $name;
    }

    private function personnelName(?Personnel $personnel): string
    {
        if (!$personnel instanceof Personnel) {
            return '—';
        }

        $name = trim(implode(' ', array_filter([
            $personnel->getNom(),
            $personnel->getPostNom(),
            $personnel->getPrenom(),
        ])));

        return '' !== $name ? $name : '—';
    }

    private function slug(EtudeImagerie $etude): string
    {
        return preg_replace('/[^A-Za-z0-9]+/', '-', (string) ($etude->getNumero() ?? $etude->getId())) ?: 'imagerie';
    }

    private function e(?string $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
