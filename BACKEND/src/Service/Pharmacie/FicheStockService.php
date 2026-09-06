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
}
