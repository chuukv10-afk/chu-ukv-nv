<?php

namespace App\Service\Pharmacie;

use App\DTO\Pharmacie\PharmacieListQuery;
use App\Entity\Medicament;
use App\Entity\MouvementStock;
use App\Exception\NotFoundException;
use App\Repository\MedicamentRepository;
use App\Repository\MouvementStockRepository;
use App\Service\Export\PdfExportService;
use App\Service\Export\TableExportService;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Validator\Exception\ValidationFailedException;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final class FicheStockService
{
    private const TYPE_LABELS = [
        MouvementStock::TYPE_ENTREE_RECEPTION => 'Entrée réception',
        MouvementStock::TYPE_SORTIE_VENTE => 'Sortie vente',
        MouvementStock::TYPE_ENTREE_ANNULATION_VENTE => 'Retour annulation vente',
        MouvementStock::TYPE_SORTIE_SERVICE => 'Sortie service',
        MouvementStock::TYPE_SORTIE_HOSPITALISE => 'Sortie hospitalisé',
        MouvementStock::TYPE_SORTIE_PERTE => 'Perte',
        MouvementStock::TYPE_SORTIE_PEREMPTION => 'Péremption',
        MouvementStock::TYPE_AJUSTEMENT_PLUS => 'Ajustement +',
        MouvementStock::TYPE_AJUSTEMENT_MOINS => 'Ajustement −',
    ];

    private const DOC_LABELS = [
        MouvementStock::DOC_RECEPTION => 'Réception',
        MouvementStock::DOC_VENTE => 'Vente',
        MouvementStock::DOC_DEMANDE_SERVICE => 'Demande service',
        MouvementStock::DOC_AJUSTEMENT => 'Ajustement',
        MouvementStock::DOC_INVENTAIRE => 'Inventaire',
    ];

    /**
     * Lignes de corps (ouverture + mouvements + vierges) sur la première page
     * d'une fiche A4 portrait, sous l'en-tête CHU. Mesuré pour ne pas déborder.
     */
    private const PRINT_FIRST_PAGE_ROWS = 31;

    /**
     * Lignes de corps sur une page de suite (en-tête de colonnes seulement).
     */
    private const PRINT_NEXT_PAGE_ROWS = 38;

    public function __construct(
        private readonly MouvementStockRepository $mouvementStockRepository,
        private readonly MedicamentRepository $medicamentRepository,
        private readonly TableExportService $tableExportService,
        private readonly PdfExportService $pdfExportService,
        private readonly ValidatorInterface $validator,
    ) {
    }

    public function export(Request $request, PharmacieListQuery $query): Response
    {
        $errors = $this->validator->validate($query);
        if (count($errors) > 0) {
            throw new ValidationFailedException($query, $errors);
        }

        $fiches = $this->buildFiches($query);
        $format = strtolower(trim((string) $request->query->get('format', 'pdf')));
        if (!\in_array($format, ['pdf', 'xlsx'], true)) {
            throw new BadRequestHttpException('Format d\'export invalide. Utilisez pdf ou xlsx.');
        }

        $title = 1 === count($fiches)
            ? 'Fiche de stock — ' . $fiches[0]['libelle']
            : 'Fiches de stock';

        if ('pdf' === $format) {
            return $this->pdfExportService->createTableDocumentResponse(
                $title,
                $this->buildPdfHtml($fiches, $query),
                'fiches_stock',
                $this->tableExportService->resolveCurrentUserDisplayName(),
                new \DateTimeImmutable(),
                'portrait',
            );
        }

        $headers = ['N°', 'Médicament', 'Date', 'Type', 'Document', 'Lot', 'Entrée', 'Sortie', 'Solde', 'Motif'];
        $rows = [];
        foreach ($fiches as $fiche) {
            $rows[] = [
                $fiche['code'] . ' — ' . $fiche['libelle'],
                $query->dateFrom ? $this->formatDate($query->dateFrom) : '—',
                'Stock d’ouverture',
                '',
                '',
                '',
                '',
                (string) $fiche['ouverture'],
                '',
            ];
            foreach ($fiche['lignes'] as $ligne) {
                $rows[] = [
                    $fiche['code'] . ' — ' . $fiche['libelle'],
                    $ligne['date'],
                    $ligne['type'],
                    $ligne['document'],
                    $ligne['lot'],
                    $ligne['entree'],
                    $ligne['sortie'],
                    (string) $ligne['solde'],
                    $ligne['motif'],
                ];
            }
            $rows[] = [
                $fiche['code'] . ' — ' . $fiche['libelle'],
                $query->dateTo ? $this->formatDate($query->dateTo) : '—',
                'Stock de clôture',
                '',
                '',
                '',
                '',
                (string) $fiche['cloture'],
                '',
            ];
        }

        return $this->tableExportService->createResponse(
            'xlsx',
            $headers,
            $rows,
            $title,
            'fiches_stock',
            'Aucun mouvement pour les filtres sélectionnés.',
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function buildFiches(PharmacieListQuery $query): array
    {
        $selected = null;
        if (null !== $query->medicamentId && $query->medicamentId > 0) {
            $selected = $this->medicamentRepository->find($query->medicamentId);
            if (null === $selected) {
                throw new NotFoundException('Médicament non trouvé.');
            }
        }

        $mouvements = $this->mouvementStockRepository->findForFiche(
            $selected?->getId(),
            $query->dateFrom,
            $query->dateTo,
        );

        $openings = [];
        if (null !== $query->dateFrom && '' !== $query->dateFrom) {
            if (null !== $selected) {
                $openings[$selected->getId()] = $this->mouvementStockRepository->signedStockBefore(
                    $selected->getId(),
                    $query->dateFrom,
                );
            } else {
                $openings = $this->mouvementStockRepository->signedStockByMedicamentBefore($query->dateFrom);
            }
        }

        $grouped = [];
        foreach ($mouvements as $mouvement) {
            $medicament = $mouvement->getLot()?->getMedicament();
            if (null === $medicament || null === $medicament->getId()) {
                continue;
            }
            $id = $medicament->getId();
            $grouped[$id] ??= [
                'medicament' => $medicament,
                'mouvements' => [],
            ];
            $grouped[$id]['mouvements'][] = $mouvement;
        }

        if (null !== $selected && !isset($grouped[$selected->getId()])) {
            $grouped[$selected->getId()] = [
                'medicament' => $selected,
                'mouvements' => [],
            ];
        }

        if (null === $selected) {
            foreach ($openings as $medicamentId => $opening) {
                if (0 === $opening || isset($grouped[$medicamentId])) {
                    continue;
                }
                $medicament = $this->medicamentRepository->find($medicamentId);
                if (null === $medicament) {
                    continue;
                }
                $grouped[$medicamentId] = [
                    'medicament' => $medicament,
                    'mouvements' => [],
                ];
            }
        }

        uasort($grouped, static function (array $left, array $right): int {
            return strcasecmp((string) $left['medicament']->getLibelle(), (string) $right['medicament']->getLibelle());
        });

        $fiches = [];
        foreach ($grouped as $id => $group) {
            /** @var Medicament $medicament */
            $medicament = $group['medicament'];
            $solde = (int) ($openings[$id] ?? 0);
            $lignes = [];

            foreach ($group['mouvements'] as $mouvement) {
                $entree = MouvementStock::SENS_ENTREE === $mouvement->getSens() ? $mouvement->getQuantite() : 0;
                $sortie = MouvementStock::SENS_SORTIE === $mouvement->getSens() ? $mouvement->getQuantite() : 0;
                $solde += $entree - $sortie;
                $lignes[] = [
                    'date' => $mouvement->getCreatedAt()?->format('d/m/Y H:i') ?? '—',
                    'type' => self::TYPE_LABELS[$mouvement->getType()] ?? (string) $mouvement->getType(),
                    'document' => sprintf('%s #%d', $mouvement->getDocumentType(), $mouvement->getDocumentId()),
                    'lot' => $mouvement->getLot()?->getNumeroLot() ?? '—',
                    'entree' => $entree > 0 ? (string) $entree : '',
                    'sortie' => $sortie > 0 ? (string) $sortie : '',
                    'solde' => $solde,
                    'motif' => $mouvement->getMotif() ?? '',
                ];
            }

            $fiches[] = [
                'code' => $medicament->getCode(),
                'libelle' => $medicament->getLibelle(),
                'unite' => $medicament->getUnite()?->getLibelle() ?? '—',
                'famille' => $medicament->getFamille()?->getLibelle() ?? '—',
                'ouverture' => (int) ($openings[$id] ?? 0),
                'cloture' => $solde,
                'lignes' => $lignes,
            ];
        }

        return $fiches;
    }

    /**
     * @param list<array<string, mixed>> $fiches
     */
    private function buildPdfHtml(array $fiches, PharmacieListQuery $query): string
    {
        if ([] === $fiches) {
            return $this->pdfExportService->buildTableHtml(
                ['N°', 'Date', 'Type', 'Document', 'Lot', 'Entrée', 'Sortie', 'Solde', 'Motif'],
                [],
                'Aucun mouvement pour les filtres sélectionnés.',
            );
        }

        $periode = $this->periodLabel($query);
        $parts = [];

        foreach ($fiches as $fiche) {
            $header = sprintf(
                '<div class="fiche-meta"><strong>%s — %s</strong><br>Unité : %s · Famille : %s<br>Période : %s<br>Stock d’ouverture : %d · Stock de clôture : %d</div>',
                $this->e((string) $fiche['code']),
                $this->e((string) $fiche['libelle']),
                $this->e((string) $fiche['unite']),
                $this->e((string) $fiche['famille']),
                $this->e($periode),
                $fiche['ouverture'],
                $fiche['cloture'],
            );

            $rows = [];
            $rows[] = [
                '',
                $query->dateFrom ? $this->formatDate($query->dateFrom) : '—',
                'Stock d’ouverture',
                '',
                '',
                '',
                '',
                (string) $fiche['ouverture'],
                '',
            ];
            foreach ($fiche['lignes'] as $index => $ligne) {
                $rows[] = [
                    (string) ($index + 1),
                    $ligne['date'],
                    $ligne['type'],
                    $ligne['document'],
                    $ligne['lot'],
                    $ligne['entree'],
                    $ligne['sortie'],
                    (string) $ligne['solde'],
                    $ligne['motif'] !== '' ? $ligne['motif'] : '—',
                ];
            }
            $rows[] = [
                '',
                $query->dateTo ? $this->formatDate($query->dateTo) : '—',
                'Stock de clôture',
                '',
                '',
                '',
                '',
                (string) $fiche['cloture'],
                '',
            ];

            $parts[] = '<div class="fiche-block" style="page-break-inside:avoid;margin-bottom:22px;">'
                . $header
                . $this->pdfExportService->buildTableHtml(
                    ['N°', 'Date', 'Type', 'Document', 'Lot', 'Entrée', 'Sortie', 'Solde', 'Motif'],
                    $rows,
                    'Aucun mouvement.',
                )
                . '</div>';
        }

        return implode('', $parts);
    }

    private function periodLabel(PharmacieListQuery $query): string
    {
        if (null !== $query->dateFrom && null !== $query->dateTo) {
            return 'du ' . $this->formatDate($query->dateFrom) . ' au ' . $this->formatDate($query->dateTo);
        }
        if (null !== $query->dateFrom) {
            return 'depuis le ' . $this->formatDate($query->dateFrom);
        }
        if (null !== $query->dateTo) {
            return 'jusqu’au ' . $this->formatDate($query->dateTo);
        }

        return 'tout l’historique';
    }

    private function formatDate(string $iso): string
    {
        $date = \DateTimeImmutable::createFromFormat('Y-m-d', $iso);

        return false === $date ? $iso : $date->format('d/m/Y');
    }

    private function e(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    public function exportImprimablesActifs(PharmacieListQuery $query): Response
    {
        $errors = $this->validator->validate($query);
        if (count($errors) > 0) {
            throw new ValidationFailedException($query, $errors);
        }

        if (!class_exists(\ZipArchive::class)) {
            throw new BadRequestHttpException('L\'extension PHP zip est requise pour générer les fiches à compléter.');
        }

        @set_time_limit(0);
        $previousLimit = ini_get('memory_limit');
        if (false !== $previousLimit && $this->memoryLimitBytes($previousLimit) < 1024 * 1024 * 1024) {
            ini_set('memory_limit', '1024M');
        }

        $zipPath = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'fiches_stock_' . bin2hex(random_bytes(8)) . '.zip';
        $zip = new \ZipArchive();
        $zipOpen = false;

        try {
            $fiches = $this->buildFichesActives($query);
            $combinedHtml = $this->pdfExportService->wrapOfficialDocument(
                $this->buildPrintableDocumentHtml($fiches, $query),
            );
            $combinedPdf = $this->pdfExportService->renderPdfBinary($combinedHtml);
            unset($combinedHtml);

            if (true !== $zip->open($zipPath, \ZipArchive::CREATE)) {
                throw new \RuntimeException('Impossible de créer l\'archive des fiches.');
            }
            $zipOpen = true;

            $zip->addFromString('toutes-les-fiches.pdf', $combinedPdf);
            unset($combinedPdf);

            $usedNames = [];
            foreach ($fiches as $fiche) {
                $singleHtml = $this->pdfExportService->wrapOfficialDocument(
                    $this->buildPrintableDocumentHtml([$fiche], $query),
                );
                $singlePdf = $this->pdfExportService->renderPdfBinary($singleHtml);
                unset($singleHtml);
                $zip->addFromString('medicaments/' . $this->printablePdfName($fiche, $usedNames), $singlePdf);
                unset($singlePdf);
            }

            $zip->close();
            $zipOpen = false;
            $content = file_get_contents($zipPath);
            if (false === $content) {
                throw new \RuntimeException('Impossible de lire l\'archive des fiches.');
            }

            $filename = 'fiches_stock_actives_' . (new \DateTimeImmutable())->format('Ymd_His') . '.zip';

            return new Response($content, Response::HTTP_OK, [
                'Content-Type' => 'application/zip',
                'Content-Disposition' => 'attachment; filename="' . $filename . '"',
                'X-Export-Filename' => $filename,
            ]);
        } finally {
            if ($zipOpen) {
                $zip->close();
            }
            if (is_file($zipPath)) {
                unlink($zipPath);
            }
            if (is_string($previousLimit) && '' !== $previousLimit) {
                ini_set('memory_limit', $previousLimit);
            }
        }
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function buildFichesActives(PharmacieListQuery $query): array
    {
        $actifs = $this->medicamentRepository->findActifs();
        $mouvements = $this->mouvementStockRepository->findForFicheActifs($query->dateFrom, $query->dateTo);

        $openings = [];
        if (null !== $query->dateFrom && '' !== $query->dateFrom) {
            $openings = $this->mouvementStockRepository->signedStockByMedicamentBefore($query->dateFrom);
        }

        $byMedicament = [];
        foreach ($mouvements as $mouvement) {
            $medicament = $mouvement->getLot()?->getMedicament();
            $id = $medicament?->getId();
            if (null === $id) {
                continue;
            }
            $byMedicament[$id][] = $mouvement;
        }

        $fiches = [];
        foreach ($actifs as $medicament) {
            $id = $medicament->getId();
            if (null === $id) {
                continue;
            }

            $solde = (int) ($openings[$id] ?? 0);
            $lignes = [];
            foreach ($byMedicament[$id] ?? [] as $mouvement) {
                $entree = MouvementStock::SENS_ENTREE === $mouvement->getSens() ? $mouvement->getQuantite() : 0;
                $sortie = MouvementStock::SENS_SORTIE === $mouvement->getSens() ? $mouvement->getQuantite() : 0;
                $solde += $entree - $sortie;
                $document = self::DOC_LABELS[$mouvement->getDocumentType()] ?? (string) $mouvement->getDocumentType();
                $lignes[] = [
                    'date' => $mouvement->getCreatedAt()?->format('d/m/Y H:i') ?? '—',
                    'type' => self::TYPE_LABELS[$mouvement->getType()] ?? (string) $mouvement->getType(),
                    'document' => sprintf('%s #%d', $document, $mouvement->getDocumentId()),
                    'lot' => $mouvement->getLot()?->getNumeroLot() ?? '—',
                    'entree' => $entree > 0 ? (string) $entree : '',
                    'sortie' => $sortie > 0 ? (string) $sortie : '',
                    'solde' => $solde,
                    'motif' => $mouvement->getMotif() ?? '',
                ];
            }

            $fiches[] = [
                'id' => $id,
                'code' => $medicament->getCode(),
                'libelle' => $medicament->getLibelle(),
                'unite' => $medicament->getUnite()?->getLibelle() ?? '—',
                'famille' => $medicament->getFamille()?->getLibelle() ?? '—',
                'ouverture' => (int) ($openings[$id] ?? 0),
                'cloture' => $solde,
                'lignes' => $lignes,
            ];
        }

        return $fiches;
    }

    /**
     * @param list<array<string, mixed>> $fiches
     */
    private function buildPrintableDocumentHtml(array $fiches, PharmacieListQuery $query): string
    {
        if ([] === $fiches) {
            return '<p style="font-size:12px;">Aucun médicament actif.</p>';
        }

        $html = $this->printableStyles();
        $lastIndex = count($fiches) - 1;
        foreach ($fiches as $index => $fiche) {
            $html .= $this->buildPrintableFicheSection($fiche, $query, $index === $lastIndex);
        }

        return $html;
    }

    /**
     * @param array<string, mixed> $fiche
     */
    private function buildPrintableFicheSection(array $fiche, PharmacieListQuery $query, bool $last): string
    {
        $class = $last ? 'fiche-print fiche-print-last' : 'fiche-print';
        $letterhead = '<header class="chu-header">' . $this->pdfExportService->renderLetterhead('portrait') . '</header>';
        $meta = sprintf(
            '<div class="fiche-print-meta"><strong>Fiche de stock — %s — %s</strong><br>Unité : %s · Famille : %s · Période : %s<br>Stock d’ouverture : %d · Stock de clôture : %d · Lignes vierges à compléter à la main</div>',
            $this->e((string) $fiche['code']),
            $this->e((string) $fiche['libelle']),
            $this->e((string) $fiche['unite']),
            $this->e((string) $fiche['famille']),
            $this->e($this->periodLabel($query)),
            $fiche['ouverture'],
            $fiche['cloture'],
        );

        $body = '';
        foreach ($this->printableRows($fiche, $query) as $row) {
            $kind = $row['kind'];
            $body .= '<tr class="fiche-' . $kind . '">';
            foreach ($row['cells'] as $columnIndex => $value) {
                $align = \in_array($columnIndex, [0, 5, 6, 7], true) ? ' qty' : '';
                $text = '' === $value ? '&nbsp;' : $this->e($value);
                $body .= '<td class="' . trim($align) . '">' . $text . '</td>';
            }
            $body .= '</tr>';
        }

        return '<section class="' . $class . '">'
            . $letterhead
            . $meta
            . '<table class="fiche-grid"><colgroup>'
            . '<col style="width:6%"><col style="width:14%"><col style="width:16%"><col style="width:14%">'
            . '<col style="width:12%"><col style="width:8%"><col style="width:8%"><col style="width:8%"><col style="width:14%">'
            . '</colgroup><thead><tr>'
            . '<th>N°</th><th>Date</th><th>Type</th><th>Document</th><th>Lot</th><th>Entrée</th><th>Sortie</th><th>Solde</th><th>Motif</th>'
            . '</tr></thead><tbody>' . $body . '</tbody></table></section>';
    }

    /**
     * @param array<string, mixed> $fiche
     * @return list<array{kind: string, cells: list<string>}>
     */
    private function printableRows(array $fiche, PharmacieListQuery $query): array
    {
        $rows = [[
            'kind' => 'ouverture',
            'cells' => [
                '',
                $query->dateFrom ? $this->formatDate($query->dateFrom) : '—',
                'Stock d’ouverture',
                '',
                '',
                '',
                '',
                (string) $fiche['ouverture'],
                '',
            ],
        ]];

        foreach ($fiche['lignes'] as $index => $ligne) {
            $rows[] = [
                'kind' => 'mouvement',
                'cells' => [
                    (string) ($index + 1),
                    (string) $ligne['date'],
                    $this->clip((string) $ligne['type'], 32),
                    $this->clip((string) $ligne['document'], 26),
                    $this->clip((string) $ligne['lot'], 18),
                    (string) $ligne['entree'],
                    (string) $ligne['sortie'],
                    (string) $ligne['solde'],
                    $this->clip((string) $ligne['motif'], 42),
                ],
            ];
        }

        $target = $this->printableTargetRows(count($rows));
        while (\count($rows) < $target) {
            $rows[] = [
                'kind' => 'vide',
                'cells' => ['', '', '', '', '', '', '', '', ''],
            ];
        }

        return $rows;
    }

    private function printableTargetRows(int $count): int
    {
        $first = self::PRINT_FIRST_PAGE_ROWS;
        $next = self::PRINT_NEXT_PAGE_ROWS;
        if ($count <= $first) {
            return $first;
        }

        $remainder = ($count - $first) % $next;
        if (0 === $remainder) {
            return $count;
        }

        return $count + ($next - $remainder);
    }

    /**
     * @param array<string, mixed> $fiche
     * @param array<string, true> $usedNames
     */
    private function printablePdfName(array $fiche, array &$usedNames): string
    {
        $code = preg_replace('/[^A-Za-z0-9._-]+/', '-', (string) $fiche['code']) ?? '';
        $code = trim((string) $code, '-.');
        if ('' === $code) {
            $code = 'medicament';
        }

        $name = $code . '-' . $fiche['id'] . '.pdf';
        if (isset($usedNames[$name])) {
            $name = $code . '-' . $fiche['id'] . '-' . count($usedNames) . '.pdf';
        }
        $usedNames[$name] = true;

        return $name;
    }

    private function clip(string $value, int $max): string
    {
        $value = trim($value);
        if (mb_strlen($value) <= $max) {
            return $value;
        }

        return mb_substr($value, 0, max(1, $max - 1)) . '…';
    }

    private function printableStyles(): string
    {
        return <<<'HTML'
<style>
.fiche-print { page-break-after: always; }
.fiche-print-last { page-break-after: auto; }
.fiche-print-meta {
    font-size: 9px;
    line-height: 1.35;
    margin: 2px 0 6px;
}
table.fiche-grid {
    width: 100%;
    border-collapse: collapse;
    table-layout: fixed;
}
table.fiche-grid th,
table.fiche-grid td {
    border: 1px solid #8b95a1;
    font-size: 8px;
    line-height: 14px;
    height: 20px;
    padding: 3px 4px;
    vertical-align: middle;
    white-space: nowrap;
    overflow: hidden;
}
table.fiche-grid th {
    background: #1E5AA8;
    color: #ffffff;
    font-weight: bold;
    text-align: center;
    height: 16px;
}
table.fiche-grid td.qty { text-align: center; }
table.fiche-grid tr.fiche-ouverture td {
    font-weight: bold;
    background: #e8eef5;
}
table.fiche-grid tr.fiche-vide td { height: 20px; }
</style>
HTML;
    }

    private function memoryLimitBytes(string $limit): int
    {
        $normalized = strtoupper(trim($limit));
        if ('-1' === $normalized) {
            return PHP_INT_MAX;
        }

        $factor = 1;
        $unit = substr($normalized, -1);
        $value = $normalized;
        if ('G' === $unit) {
            $factor = 1024 * 1024 * 1024;
            $value = substr($normalized, 0, -1);
        } elseif ('M' === $unit) {
            $factor = 1024 * 1024;
            $value = substr($normalized, 0, -1);
        } elseif ('K' === $unit) {
            $factor = 1024;
            $value = substr($normalized, 0, -1);
        }

        return (int) $value * $factor;
    }
}
