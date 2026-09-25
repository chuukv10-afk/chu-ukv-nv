<?php

namespace App\Service\Imagerie;

use App\Entity\EtudeImagerie;
use App\Entity\Patient;
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
            '',
            $this->renderBonBody($etude, $demandeur),
            $demandeur,
            $etude->getCreatedAt(),
            'portrait',
            null,
            null,
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
            '',
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
        $lines = $this->identityLines([
            'Nom du patient' => $patient?->getFullName() ?? '—',
            'Examen demandé' => $etude->getExamen()?->getLibelle() ?? '—',
            'Indication' => $etude->getIndication() ?: '—',
            'But' => $etude->getBut() ?: '—',
            'Date de la demande' => $etude->getCreatedAt()?->format('d/m/Y') ?? '—',
            'Médecin demandeur' => $this->doctorLabel($etude->getDemandePar()) ?: $demandeur,
        ], $patient);

        return <<<HTML
{$lines}
<div class="cr-sep"></div>
<div class="cr-title">Bon de demande d'examen</div>
<p><strong>Indication :</strong></p>
<div class="cr-result">{$this->richText($etude->getIndication())}</div>
<p><strong>But :</strong></p>
<div class="cr-result">{$this->richText($etude->getBut())}</div>
<div class="cr-sign">
    <p class="cr-date">{$this->e($this->layoutProvider->formatOfficialDateLine($etude->getCreatedAt()))}</p>
    <p class="cr-role">Le Médecin demandeur</p>
    <p class="cr-doctor"><strong>{$this->e($this->doctorLabel($etude->getDemandePar()) ?: $demandeur)}</strong></p>
</div>
{$this->documentStyles()}
HTML;
    }

    private function renderCompteRenduBody(
        EtudeImagerie $etude,
        string $signerName,
        bool $validated,
        ?\DateTimeInterface $date,
    ): string {
        $patient = $etude->getPatient();
        $banner = $validated ? '' : '<div class="cr-banner">PROJET — EN ATTENTE DE VALIDATION MÉDICALE</div>';
        $doctor = $this->e($this->doctorLabel($validated ? $etude->getValidePar() : $etude->getInterpretePar()) ?: $signerName);
        $dateLine = $date instanceof \DateTimeInterface
            ? sprintf('Fait à Boma, le %s', $date->format('d / m / Y'))
            : 'Fait à Boma, le —';
        $role = $validated ? 'Le Médecin' : 'Le Médecin (en attente de validation)';
        $signature = $validated ? $this->signatureImage($etude->getValidePar()) : '';
        $protocolDate = $etude->getInterpreteAt() ?? $etude->getCreatedAt();
        $lines = $this->identityLines([
            'Nom du patient' => $patient?->getFullName() ?? '—',
            'Examen réalisé' => $etude->getExamen()?->getLibelle() ?? '—',
            'Indication' => $etude->getIndication() ?: '—',
            'Protocolé le' => $protocolDate?->format('d/m/Y') ?? '—',
            'Médecin demandeur' => $this->doctorLabel($etude->getDemandePar()) ?: '—',
        ], $patient);
        $resultat = $this->resultatHtml($etude);

        return <<<HTML
{$banner}
{$lines}
<div class="cr-sep"></div>
<div class="cr-title">Compte rendu de l'examen</div>
<p><strong>Résultat :</strong></p>
<div class="cr-result">{$resultat}</div>
<p class="cr-thanks">Merci de nous avoir confié votre patient.</p>
<div class="cr-sign">
    <p class="cr-date">{$this->e($dateLine)}</p>
    {$signature}
    <p class="cr-role">{$this->e($role)}</p>
    <p class="cr-doctor"><strong>{$doctor}</strong></p>
</div>
{$this->documentStyles()}
HTML;
    }

    /**
     * @param array<string, string> $fields
     */
    private function identityLines(array $fields, ?Patient $patient): string
    {
        $rows = '';
        foreach ($fields as $label => $value) {
            $rows .= sprintf(
                '<div class="cr-line"><span class="cr-k">%s :</span> %s</div>',
                $this->e($label),
                $this->e($value),
            );
        }

        return sprintf(
            '<div class="cr-id">
                <div class="cr-id-left">%s</div>
                <div class="cr-id-right">
                    <div class="cr-line"><span class="cr-k">Sexe :</span> %s</div>
                    <div class="cr-line"><span class="cr-k">Age :</span> %s</div>
                </div>
            </div>',
            $rows,
            $this->e($this->sexeCode($patient?->getSexe())),
            $this->e($this->ageLabel($patient?->getDateNaissance())),
        );
    }

    private function resultatHtml(EtudeImagerie $etude): string
    {
        $parts = [];
        if ('' !== trim((string) $etude->getTechnique())) {
            $parts[] = '<p><em>Technique :</em> ' . $this->richText($etude->getTechnique()) . '</p>';
        }
        $parts[] = '<p>' . $this->richText($etude->getConstatations()) . '</p>';
        if ('' !== trim((string) $etude->getConclusion())) {
            $parts[] = '<p>' . $this->richText($etude->getConclusion()) . '</p>';
        }

        return implode('', $parts);
    }

    private function documentStyles(): string
    {
        return <<<'CSS'
<style>
h1.report-title { display: none; }
p { margin: 4px 0; line-height: 1.45; font-size: 11px; }
.cr-banner {
    text-align: center;
    margin: 0 0 10px;
    padding: 5px 8px;
    border: 1.5px solid #c0392b;
    color: #c0392b;
    font-weight: bold;
    letter-spacing: 1px;
    font-size: 10px;
}
.cr-id { display: table; width: 100%; font-size: 11px; line-height: 1.55; }
.cr-id-left { display: table-cell; vertical-align: top; }
.cr-id-right { display: table-cell; width: 150px; text-align: right; vertical-align: top; white-space: nowrap; }
.cr-line { margin: 0 0 1px; }
.cr-k { font-weight: bold; }
.cr-sep { border-top: 1px solid #222; margin: 10px 0 16px; }
.cr-title {
    text-align: center;
    font-size: 13px;
    font-weight: bold;
    letter-spacing: 0.8px;
    text-transform: uppercase;
    margin: 0 0 16px;
}
.cr-result { font-size: 11px; line-height: 1.5; min-height: 40px; }
.cr-thanks { margin-top: 28px; font-size: 10px; }
.cr-sign { margin-top: 22px; text-align: right; page-break-inside: avoid; }
.cr-date { margin: 0 0 8px; font-size: 10px; }
.cr-role { margin: 2px 0 0; font-size: 9px; color: #444; }
.cr-doctor { margin: 2px 0 0; font-size: 11px; }
.cap-signature-img { display: block; max-height: 52px; max-width: 160px; margin: 0 0 4px auto; }
</style>
CSS;
    }

    private function richText(?string $value): string
    {
        $text = trim((string) $value);

        return '' === $text ? '—' : nl2br($this->e($text));
    }

    private function sexeCode(?string $sexe): string
    {
        return in_array($sexe, ['M', 'F'], true) ? $sexe : '—';
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

        return '<img class="cap-signature-img" src="' . $dataUri . '" alt="Signature" />';
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
