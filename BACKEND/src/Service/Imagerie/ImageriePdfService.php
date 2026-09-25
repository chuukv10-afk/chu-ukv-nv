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
            'Compte-rendu d\'imagerie médicale',
            $this->renderCompteRenduBody($etude, $signerName, $validated, $date),
            $signerName,
            $date,
            'portrait',
            null,
            null,
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

    private function renderCompteRenduBody(
        EtudeImagerie $etude,
        string $signerName,
        bool $validated,
        ?\DateTimeInterface $date,
    ): string {
        $patient = $etude->getPatient();
        $banner = $validated
            ? ''
            : '<div class="cr-banner">PROJET — EN ATTENTE DE VALIDATION MÉDICALE</div>';
        $doctor = $this->e($this->doctorLabel($validated ? $etude->getValidePar() : $etude->getInterpretePar()) ?: $signerName);
        $dateLine = $date instanceof \DateTimeInterface
            ? sprintf('Fait à Boma, le %s', $date->format('d / m / Y'))
            : 'Fait à Boma, le —';
        $role = $validated ? 'Le Médecin validateur' : 'Le Médecin interprétant';
        $signature = $validated ? $this->signatureImage($etude->getValidePar()) : '';

        return <<<HTML
{$banner}
<div class="cap-meta">
    <span>N° <strong>{$this->e((string) $etude->getNumero())}</strong></span>
    <span>Date de l'examen : <strong>{$this->e($etude->getCreatedAt()?->format('d/m/Y') ?? '—')}</strong></span>
    <span>Statut : <strong>{$this->e($validated ? 'Validé' : 'Interprété')}</strong></span>
</div>

<h2 class="cap-section">I. Identification du patient</h2>
<table class="cr-grid">
    <tr>
        <td><span class="cr-label">Nom, post-nom et prénom</span><br><strong>{$this->e($patient?->getFullName() ?? '—')}</strong></td>
        <td><span class="cr-label">Sexe / Âge</span><br><strong>{$this->e($this->sexeLabel($patient?->getSexe()))} &nbsp;·&nbsp; {$this->e($this->ageLabel($patient?->getDateNaissance()))}</strong></td>
    </tr>
    <tr>
        <td><span class="cr-label">Né(e) le</span><br>{$this->e($patient?->getDateNaissance()?->format('d/m/Y') ?? '—')}</td>
        <td><span class="cr-label">N° dossier / UKV</span><br>{$this->e($patient?->getCodeUkv() ?: '—')}</td>
    </tr>
</table>

<h2 class="cap-section">II. Demande d'examen</h2>
<table class="cr-grid">
    <tr>
        <td><span class="cr-label">Examen demandé</span><br><strong>{$this->e($etude->getExamen()?->getLibelle() ?? '—')}</strong></td>
        <td><span class="cr-label">Type</span><br>{$this->e($etude->getExamen()?->getTypeExamen()?->getLibelle() ?: 'Imagerie')}</td>
    </tr>
    <tr>
        <td><span class="cr-label">But</span><br>{$this->e($etude->getBut() ?: '—')}</td>
        <td><span class="cr-label">Médecin demandeur</span><br>{$this->e($this->doctorLabel($etude->getDemandePar()) ?: '—')}</td>
    </tr>
</table>

<h2 class="cap-section">III. Renseignements cliniques</h2>
<div class="cr-box">{$this->richText($etude->getIndication())}</div>

<h2 class="cap-section">IV. Technique</h2>
<div class="cr-box">{$this->richText($etude->getTechnique())}</div>

<h2 class="cap-section">V. Constatations</h2>
<div class="cr-findings">{$this->richText($etude->getConstatations())}</div>

<h2 class="cap-section">VI. Conclusion</h2>
<div class="cr-conclusion">{$this->richText($etude->getConclusion())}</div>

<div class="cr-sign">
    <p class="cr-date">{$this->e($dateLine)}</p>
    {$signature}
    <p class="cr-role">{$this->e($role)}</p>
    <p class="cr-doctor"><strong>{$doctor}</strong></p>
</div>
{$this->documentStyles()}
HTML;
    }

    private function documentStyles(): string
    {
        return <<<'CSS'
<style>
p { margin: 4px 0; line-height: 1.35; }
.cap-meta { display: table; width: 100%; margin: 0 0 10px; font-size: 10px; }
.cap-meta span { display: table-cell; }
.cap-meta span:last-child { text-align: right; }
.cap-section { font-size: 11px; margin: 14px 0 5px; text-transform: uppercase; color: #1E5AA8; letter-spacing: 0.3px; }
.cap-signature-img { display: block; max-height: 52px; max-width: 160px; margin: 0 0 4px auto; }
.cr-banner {
    text-align: center;
    margin: 0 0 10px;
    padding: 6px 8px;
    border: 1.5px solid #c0392b;
    color: #c0392b;
    font-weight: bold;
    letter-spacing: 1px;
    font-size: 10px;
}
.cr-grid { width: 100%; border-collapse: collapse; margin: 0 0 4px; }
.cr-grid td {
    border: 1px solid #d0d7de;
    padding: 6px 8px;
    width: 50%;
    vertical-align: top;
    font-size: 10px;
}
.cr-label { font-size: 8px; color: #555; text-transform: uppercase; letter-spacing: 0.3px; }
.cr-box, .cr-findings, .cr-conclusion {
    border: 1px solid #d0d7de;
    padding: 8px 10px;
    font-size: 10.5px;
    line-height: 1.45;
    min-height: 28px;
    background: #fafbfc;
}
.cr-findings { min-height: 72px; background: #fff; }
.cr-conclusion {
    border: 1.5px solid #1E5AA8;
    background: #f3f7fb;
    font-size: 11px;
    font-weight: bold;
    min-height: 40px;
}
.cr-sign { margin-top: 28px; text-align: right; page-break-inside: avoid; }
.cr-date { margin: 0 0 8px; font-size: 10px; }
.cr-role { margin: 2px 0 0; font-size: 9px; color: #444; }
.cr-doctor { margin: 2px 0 0; font-size: 11px; }
</style>
CSS;
    }

    private function richText(?string $value): string
    {
        $text = trim((string) $value);

        return '' === $text ? '—' : nl2br($this->e($text));
    }

    private function sexeLabel(?string $sexe): string
    {
        return match ($sexe) {
            'M' => 'Masculin',
            'F' => 'Féminin',
            default => '—',
        };
    }

    private function ageLabel(?\DateTimeInterface $naissance): string
    {
        if (!$naissance instanceof \DateTimeInterface) {
            return '—';
        }
        $now = new \DateTimeImmutable('now', new \DateTimeZone('Africa/Kinshasa'));
        $years = $now->diff(\DateTimeImmutable::createFromInterface($naissance))->y;

        return $years . ' an' . (1 === $years ? '' : 's');
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
