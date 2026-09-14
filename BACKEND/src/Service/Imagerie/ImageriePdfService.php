<?php

namespace App\Service\Imagerie;

use App\Entity\EtudeImagerie;
use App\Exception\ConflictException;
use App\Service\Export\ChuPdfLayoutProvider;
use App\Service\Export\PdfExportService;
use Symfony\Component\HttpFoundation\Response;

final class ImageriePdfService
{
    public function __construct(
        private readonly ChuPdfLayoutProvider $layoutProvider,
        private readonly PdfExportService $pdfExportService,
    ) {
    }

    public function createResponse(EtudeImagerie $etude): Response
    {
        if (!in_array($etude->getStatut(), [EtudeImagerie::STATUT_INTERPRETE, EtudeImagerie::STATUT_VALIDE], true)) {
            throw new ConflictException('Le compte-rendu n\'est disponible qu\'après interprétation.');
        }

        $doctor = $etude->getInterpretePar();
        $doctorName = $doctor
            ? trim(implode(' ', array_filter([$doctor->getNom(), $doctor->getPostNom(), $doctor->getPrenom()])))
            : '—';

        $html = $this->layoutProvider->buildDocument(
            'Compte-rendu d\'imagerie',
            $this->renderBody($etude, $doctorName),
            $doctorName,
            $etude->getInterpreteAt() ?? $etude->getCreatedAt(),
            'portrait',
            $this->layoutProvider->formatOfficialDateLine($etude->getInterpreteAt() ?? $etude->getCreatedAt()),
            $doctorName,
        );

        $slug = preg_replace('/[^A-Za-z0-9]+/', '-', (string) ($etude->getNumero() ?? $etude->getId())) ?: 'imagerie';

        return $this->pdfExportService->createDownloadResponse(
            $html,
            'compte-rendu-imagerie-' . $slug . '.pdf',
            'portrait',
            inline: true,
        );
    }

    private function renderBody(EtudeImagerie $etude, string $doctorName): string
    {
        $patient = $etude->getPatient();
        $examen = $etude->getExamen();
        $statut = EtudeImagerie::STATUT_VALIDE === $etude->getStatut() ? 'Validé' : 'Interprété';

        return sprintf(
            '<div class="cap-meta"><span>N° <strong>%s</strong></span><span>Statut : <strong>%s</strong></span></div>
            <h2 class="cap-section">Patient</h2>
            <p><strong>Nom :</strong> %s</p>
            <h2 class="cap-section">Examen</h2>
            <p><strong>Acte :</strong> %s</p>
            <p><strong>Indication :</strong> %s</p>
            <p><strong>Technique :</strong> %s</p>
            <h2 class="cap-section">Constatations</h2>
            <p>%s</p>
            <h2 class="cap-section">Conclusion</h2>
            <p><strong>%s</strong></p>
            <p><em>Interprété par %s%s. Le partage vers d\'autres services sera disponible prochainement.</em></p>',
            $this->e((string) $etude->getNumero()),
            $this->e($statut),
            $this->e($patient?->getFullName() ?? '—'),
            $this->e($examen?->getLibelle() ?? '—'),
            $this->e($etude->getIndication() ?: '—'),
            $this->e($etude->getTechnique() ?: '—'),
            nl2br($this->e($etude->getConstatations() ?: '—')),
            nl2br($this->e($etude->getConclusion() ?: '—')),
            $this->e($doctorName),
            $etude->getInterpreteAt() ? ' le ' . $etude->getInterpreteAt()->format('d/m/Y') : '',
        );
    }

    private function e(?string $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
