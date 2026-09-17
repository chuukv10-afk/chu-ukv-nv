<?php

namespace App\Service\Pharmacie;

use App\DTO\Common\PaginatedResult;
use App\DTO\Pharmacie\CreateMedicamentInput;
use App\DTO\Pharmacie\UpdateMedicamentInput;
use App\DTO\Referentiel\ReferentielListQuery;
use App\Entity\FamilleMedicament;
use App\Entity\Medicament;
use App\Entity\UniteMedicament;
use App\Exception\ConflictException;
use App\Exception\NotFoundException;
use App\Repository\FamilleMedicamentRepository;
use App\Repository\LotRepository;
use App\Repository\MedicamentRepository;
use App\Repository\UniteMedicamentRepository;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Validator\Exception\ValidationFailedException;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final class MedicamentService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly MedicamentRepository $medicamentRepository,
        private readonly UniteMedicamentRepository $uniteMedicamentRepository,
        private readonly FamilleMedicamentRepository $familleMedicamentRepository,
        private readonly LotRepository $lotRepository,
        private readonly StockService $stockService,
        private readonly ValidatorInterface $validator,
    ) {
    }

    public function paginate(ReferentielListQuery $query): PaginatedResult
    {
        $errors = $this->validator->validate($query);
        if (count($errors) > 0) {
            throw new ValidationFailedException($query, $errors);
        }

        $result = $this->medicamentRepository->paginate($query->page, $query->limit, $query->search, $query->statut);

        return new PaginatedResult(
            array_map([$this, 'serializeSummary'], $result['items']),
            $query->page,
            $query->limit,
            $result['total'],
        );
    }

    /**
     * @return array{rows: list<list<string|null>>, summaryRows: list<list<string|null>>}
     */
    public function buildExportData(ReferentielListQuery $query, string $format = 'xlsx', bool $includeValeur = false): array
    {
        $errors = $this->validator->validate($query);
        if (count($errors) > 0) {
            throw new ValidationFailedException($query, $errors);
        }

        $rows = [];
        $totalValeur = 0.0;
        foreach ($this->medicamentRepository->findForExport($query->search, $query->statut) as $medicament) {
            $stock = $this->stockService->stockDisponible($medicament);
            $valeur = $stock * (float) $medicament->getPrixVente();
            $totalValeur += $valeur;
            $rows[] = $this->buildExportRow($medicament, $format, $includeValeur, $stock, $valeur);
        }

        $summaryRows = [];
        if ($includeValeur && [] !== $rows) {
            $summaryRows[] = $this->buildExportTotalRow($format, $totalValeur);
        }

        return [
            'rows' => $rows,
            'summaryRows' => $summaryRows,
        ];
    }

    /**
     * @return list<string>
     */
    public function exportHeaders(string $format = 'xlsx', bool $includeValeur = false, ?\DateTimeImmutable $today = null): array
    {
        $today ??= $this->stockService->today();
        $stockHeader = 'Stock (' . $today->format('d/m/Y') . ')';

        if ('pdf' === $format) {
            $headers = ['N°', 'Libellé', 'Unité', 'Prix de vente', $stockHeader];
            if ($includeValeur) {
                $headers[] = 'Valeur';
            }

            return $headers;
        }

        $headers = [
            'N°',
            'Code',
            'Libellé',
            'DCI',
            'Forme',
            'Dosage',
            'Unité',
            'Famille',
            'Prix vente',
            'Seuil',
            $stockHeader,
            'Statut',
        ];
        if ($includeValeur) {
            $headers[] = 'Valeur';
        }

        return $headers;
    }

    /**
     * @return list<string|null>
     */
    private function buildExportRow(
        Medicament $medicament,
        string $format,
        bool $includeValeur,
        int $stock,
        float $valeur,
    ): array {
        $prix = $this->formatPrixExport((float) $medicament->getPrixVente());
        $stockLabel = (string) $stock;

        if ('pdf' === $format) {
            $row = [
                $medicament->getLibelle(),
                $medicament->getUnite()?->getCode(),
                $prix,
                $stockLabel,
            ];
            if ($includeValeur) {
                $row[] = $this->formatPrixExport($valeur);
            }

            return $row;
        }

        $row = [
            $medicament->getCode(),
            $medicament->getLibelle(),
            $medicament->getDci(),
            $medicament->getForme(),
            $medicament->getDosage(),
            $medicament->getUnite()?->getCode(),
            $medicament->getFamille()?->getLibelle(),
            $prix,
            (string) $medicament->getSeuilAlerte(),
            $stockLabel,
            $medicament->getStatut(),
        ];
        if ($includeValeur) {
            $row[] = $this->formatPrixExport($valeur);
        }

        return $row;
    }

    /**
     * @return list<string|null>
     */
    private function buildExportTotalRow(string $format, float $totalValeur): array
    {
        $totalLabel = $this->formatPrixExport($totalValeur);
        if ('pdf' === $format) {
            return ['Total général', '', '', '', $totalLabel];
        }

        return ['', 'Total général', '', '', '', '', '', '', '', '', '', $totalLabel];
    }

    private function formatPrixExport(float $value): string
    {
        return number_format($value, 2, ',', ' ');
    }

    /** @return list<array<string, mixed>> */
    public function listActifs(): array
    {
        return array_map(
            [$this, 'serializeSummary'],
            $this->medicamentRepository->findActifs(),
        );
    }

    public function create(CreateMedicamentInput $input): Medicament
    {
        $errors = $this->validator->validate($input);
        if (count($errors) > 0) {
            throw new ValidationFailedException($input, $errors);
        }

        $normalizedCode = strtoupper(trim($input->code));
        if ($this->medicamentRepository->findOneBy(['code' => $normalizedCode])) {
            throw new ConflictException('Ce code médicament existe déjà.');
        }

        $medicament = (new Medicament())
            ->setCode($normalizedCode)
            ->setCreatedAt(new \DateTimeImmutable());
        $this->applyPayload($medicament, $input);

        $this->entityManager->persist($medicament);

        try {
            $this->entityManager->flush();
        } catch (UniqueConstraintViolationException) {
            throw new ConflictException('Ce code médicament existe déjà.');
        }

        return $medicament;
    }

    public function update(int $id, UpdateMedicamentInput $input): Medicament
    {
        $errors = $this->validator->validate($input);
        if (count($errors) > 0) {
            throw new ValidationFailedException($input, $errors);
        }

        $medicament = $this->getById($id);
        $this->applyPayload($medicament, $input);
        $this->entityManager->flush();

        return $medicament;
    }

    public function delete(int $id): void
    {
        $medicament = $this->getById($id);
        if ($this->lotRepository->countByMedicament($medicament) > 0) {
            throw new ConflictException('Ce médicament a déjà des lots en stock.');
        }
        $this->entityManager->remove($medicament);
        $this->entityManager->flush();
    }

    public function getById(int $id): Medicament
    {
        $medicament = $this->medicamentRepository->find($id);
        if (null === $medicament) {
            throw new NotFoundException('Médicament non trouvé.');
        }

        return $medicament;
    }

    /** @return array<string, mixed> */
    public function serializeSummary(Medicament $medicament): array
    {
        $unite = $medicament->getUnite();
        $famille = $medicament->getFamille();

        return [
            'id' => $medicament->getId(),
            'code' => $medicament->getCode(),
            'libelle' => $medicament->getLibelle(),
            'dci' => $medicament->getDci(),
            'forme' => $medicament->getForme(),
            'dosage' => $medicament->getDosage(),
            'uniteId' => $unite?->getId(),
            'unite' => $unite ? [
                'id' => $unite->getId(),
                'code' => $unite->getCode(),
                'libelle' => $unite->getLibelle(),
            ] : null,
            'familleId' => $famille?->getId(),
            'famille' => $famille ? [
                'id' => $famille->getId(),
                'code' => $famille->getCode(),
                'libelle' => $famille->getLibelle(),
            ] : null,
            'prixVente' => $medicament->getPrixVente(),
            'seuilAlerte' => $medicament->getSeuilAlerte(),
            'stockDisponible' => $this->stockService->stockDisponible($medicament),
            'statut' => $medicament->getStatut(),
            'createdAt' => $medicament->getCreatedAt()?->format(\DateTimeInterface::ATOM),
        ];
    }

    private function applyPayload(Medicament $medicament, CreateMedicamentInput|UpdateMedicamentInput $input): void
    {
        $medicament
            ->setLibelle(trim($input->libelle))
            ->setDci($this->nullableTrim($input->dci))
            ->setForme($this->nullableTrim($input->forme))
            ->setDosage($this->nullableTrim($input->dosage))
            ->setUnite($this->resolveUnite($input->uniteId))
            ->setFamille($this->resolveFamille($input->familleId))
            ->setPrixVente($this->normalizePrix($input->prixVente))
            ->setSeuilAlerte($input->seuilAlerte)
            ->setStatut($this->normalizeStatut($input->statut));
    }

    private function resolveUnite(int $id): UniteMedicament
    {
        $unite = $this->uniteMedicamentRepository->find($id);
        if (null === $unite) {
            throw new NotFoundException('Unité de médicament non trouvée.');
        }
        if (UniteMedicament::STATUT_ACTIF !== $unite->getStatut()) {
            throw new ConflictException('Cette unité est inactive.');
        }

        return $unite;
    }

    private function resolveFamille(int $id): FamilleMedicament
    {
        $famille = $this->familleMedicamentRepository->find($id);
        if (null === $famille) {
            throw new NotFoundException('Famille de médicament non trouvée.');
        }
        if (FamilleMedicament::STATUT_ACTIF !== $famille->getStatut()) {
            throw new ConflictException('Cette famille est inactive.');
        }

        return $famille;
    }

    private function normalizePrix(string $prix): string
    {
        $normalized = str_replace(',', '.', trim($prix));
        if (!is_numeric($normalized) || (float) $normalized < 0) {
            throw new ConflictException('Prix de vente invalide.');
        }

        return number_format((float) $normalized, 4, '.', '');
    }

    private function normalizeStatut(string $statut): string
    {
        $normalized = strtoupper(trim($statut));
        if (!in_array($normalized, Medicament::getStatuts(), true)) {
            throw new ConflictException('Statut invalide.');
        }

        return $normalized;
    }

    private function nullableTrim(?string $value): ?string
    {
        if (null === $value) {
            return null;
        }

        $trimmed = trim($value);

        return '' === $trimmed ? null : $trimmed;
    }
}
