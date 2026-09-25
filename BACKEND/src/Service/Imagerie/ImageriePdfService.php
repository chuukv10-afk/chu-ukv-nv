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
            null,
            true,
            false,
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
        $identity = $this->identityTable([
            'Nom du patient' => $this->upper($patient?->getFullName()),
            'Examen demandé' => $etude->getExamen()?->getLibelle() ?? '—',
            'Indication' => $etude->getIndication() ?: '—',
            'But' => $etude->getBut() ?: '—',
            'Date de la demande' => $etude->getCreatedAt()?->format('d/m/Y') ?? '—',
            'Source' => EtudeImagerie::SOURCE_EXTERNE === $etude->getSource() ? 'Externe' : 'Interne',
            'Établissement' => $etude->getEtablissement() ?: (EtudeImagerie::SOURCE_EXTERNE === $etude->getSource() ? '—' : 'CHU UKV'),
            'Médecin demandeur' => $this->demandeurNom($etude) ?: $demandeur,
        ], $patient);

        return <<<HTML
{$this->documentStyles()}
{$identity}
<hr class="cr-rule" />
<p class="cr-title">BON DE DEMANDE D'EXAMEN</p>
<p><strong>Indication :</strong></p>
{$this->plainParagraphs([$etude->getIndication()])}
<p><strong>But :</strong></p>
{$this->plainParagraphs([$etude->getBut()])}
<table class="cr-sign-table">
    <tr>
        <td>
            <div>{$this->e($this->doctorLabel($etude->getDemandePar()) ?: $demandeur)}</div>
        </td>
    </tr>
</table>
HTML;
    }

    private function renderCompteRenduBody(
        EtudeImagerie $etude,
        string $signerName,
        bool $validated,
        ?\DateTimeInterface $date,
    ): string {
        $patient = $etude->getPatient();
        $doctor = $this->e($this->doctorLabel($validated ? $etude->getValidePar() : $etude->getInterpretePar()) ?: $signerName);
        $signature = $validated ? $this->signatureImage($etude->getValidePar()) : '';
        $protocolDate = $etude->getInterpreteAt() ?? $etude->getCreatedAt();
        $identity = $this->identityTable([
            'Nom du patient' => $this->upper($patient?->getFullName()),
            'Examen réalisé' => $etude->getExamen()?->getLibelle() ?? '—',
            'Indication' => $etude->getIndication() ?: '—',
            'Protocolé le' => $protocolDate?->format('d/m/Y') ?? '—',
            'Source' => EtudeImagerie::SOURCE_EXTERNE === $etude->getSource() ? 'Externe' : 'Interne',
            'Établissement' => $etude->getEtablissement() ?: (EtudeImagerie::SOURCE_EXTERNE === $etude->getSource() ? '—' : 'CHU UKV'),
            'Médecin demandeur' => $this->demandeurNom($etude) ?: '—',
        ], $patient);
        $resultat = $this->resultatHtml($etude);

        return <<<HTML
{$this->documentStyles()}
{$identity}
<hr class="cr-rule" />
<p class="cr-title">COMPTE RENDU DE L'EXAMEN</p>
<p><strong>Résultat :</strong></p>
{$resultat}
<p class="cr-thanks">Merci de nous avoir confié votre patient.</p>
<table class="cr-sign-table">
    <tr>
        <td>
            {$signature}
            <div>{$doctor}</div>
        </td>
    </tr>
</table>
HTML;
    }

    /**
     * @param array<string, string> $fields
     */
    private function identityTable(array $fields, ?Patient $patient): string
    {
        $left = '';
        foreach ($fields as $label => $value) {
            $left .= sprintf(
                '<div class="cr-line"><strong>%s :</strong> %s</div>',
                $this->e($label),
                $this->e($value),
            );
        }

        return sprintf(
            '<table class="cr-id-table"><tr>
                <td class="cr-id-left">%s</td>
                <td class="cr-id-right">
                    <div class="cr-line"><strong>Sexe :</strong> %s</div>
                    <div class="cr-line"><strong>Age :</strong> %s</div>
                </td>
            </tr></table>',
            $left,
            $this->e($this->sexeCode($patient?->getSexe())),
            $this->e($this->ageLabel($patient?->getDateNaissance())),
        );
    }

    private function resultatHtml(EtudeImagerie $etude): string
    {
        $text = trim((string) $etude->getConstatations());
        if ('' === $text) {
            $text = trim(implode("\n\n", array_filter([
                trim((string) $etude->getTechnique()),
                trim((string) $etude->getConclusion()),
            ], static fn (string $part): bool => '' !== $part)));
        }

        return $this->formatResultat($text);
    }

    /**
     * @param list<?string> $blocks
     */
    private function plainParagraphs(array $blocks): string
    {
        $html = '';
        foreach ($blocks as $block) {
            $text = trim((string) $block);
            if ('' === $text) {
                continue;
            }
            $html .= $this->formatResultat($text);
        }

        return '' !== $html ? $html : '<p class="cr-result">—</p>';
    }

    private function formatResultat(string $text): string
    {
        if ('' === $text) {
            return '<p class="cr-result">—</p>';
        }

        $html = '';
        $listItems = [];
        $flushList = static function () use (&$html, &$listItems): void {
            if ([] === $listItems) {
                return;
            }
            $html .= '<ul class="cr-list"><li>' . implode('</li><li>', $listItems) . '</li></ul>';
            $listItems = [];
        };

        foreach (preg_split('/\R/u', $text) ?: [] as $line) {
            $trimmed = trim($line);
            if (preg_match('/^[-–—•]\s+(.*)$/u', $trimmed, $matches)) {
                $listItems[] = $this->e($matches[1]);
                continue;
            }
            $flushList();
            if ('' === $trimmed) {
                continue;
            }
            $html .= '<p class="cr-result">' . $this->e($trimmed) . '</p>';
        }
        $flushList();

        return '' !== $html ? $html : '<p class="cr-result">—</p>';
    }

    private function documentStyles(): string
    {
        return <<<'CSS'
<style>
.chu-content { font-size: 12px; color: #111; }
p { margin: 0 0 8px; line-height: 1.55; font-size: 12px; color: #111; }
.cr-id-table { width: 100%; border-collapse: collapse; margin: 6px 0 0; }
.cr-id-left { vertical-align: top; font-size: 12px; line-height: 1.7; }
.cr-id-right { width: 150px; text-align: right; vertical-align: top; font-size: 12px; line-height: 1.7; white-space: nowrap; }
.cr-line { margin: 0; }
.cr-rule { border: none; border-top: 1px solid #222; margin: 12px 0 18px; }
.cr-title { text-align: center; font-size: 14px; font-weight: bold; letter-spacing: 1px; margin: 0 0 18px; color: #111; }
.cr-result { font-size: 12px; line-height: 1.6; text-align: justify; margin: 0 0 10px; }
.cr-thanks { margin-top: 28px; font-size: 12px; text-align: center; }
.cr-list { margin: 4px 0 12px 18px; padding: 0; }
.cr-list li { margin: 0 0 4px; line-height: 1.55; }
.cr-sign-table { width: 100%; margin-top: 20px; }
.cr-sign-table td { text-align: right; font-size: 12px; }
.cap-signature-img { display: block; max-height: 52px; max-width: 160px; margin: 0 0 4px auto; }
</style>
CSS;
    }

    private function upper(?string $value): string
    {
        $text = trim((string) $value);

        return '' === $text ? '—' : mb_strtoupper($text, 'UTF-8');
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

    private function demandeurNom(EtudeImagerie $etude): string
    {
        $free = trim((string) $etude->getDemandeParNom());
        if ('' !== $free) {
            return str_starts_with(mb_strtolower($free), 'dr') ? $free : 'Dr. ' . $free;
        }

        return $this->doctorLabel($etude->getDemandePar());
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
