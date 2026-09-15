<?php

namespace App\Service\Facturation;

use App\DTO\Common\PaginatedResult;
use App\DTO\Facturation\FacturationListQuery;
use App\Entity\ActeFinancier;
use App\Exception\ConflictException;
use App\Exception\NotFoundException;
use App\Repository\ActeFinancierRepository;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Validator\Exception\ValidationFailedException;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final class ActeFinancierService
{
    public function __construct(
        private readonly ActeFinancierRepository $acteFinancierRepository,
        private readonly GrilleTarifaireImportService $grilleTarifaireImportService,
        private readonly ValidatorInterface $validator,
    ) {
    }

    public function paginate(FacturationListQuery $query): PaginatedResult
    {
        $this->assertValid($query);
        $result = $this->acteFinancierRepository->paginate(
            $query->page,
            $query->limit,
            $query->search,
            $query->serviceGrille,
            $query->statut,
        );

        return new PaginatedResult(
            array_map([$this, 'serializeSummary'], $result['items']),
            $query->page,
            $query->limit,
            $result['total'],
        );
    }

    /**
     * @return list<list<string|null>>
     */
    public function buildExportRows(FacturationListQuery $query): array
    {
        $this->assertValid($query);
        $items = $this->acteFinancierRepository->findForExport($query->search, $query->serviceGrille);

        return array_map(
            fn (ActeFinancier $acte): array => [
                $acte->getCode(),
                $acte->getServiceGrille(),
                $acte->getSousCategorie(),
                $acte->getLibelle(),
                $acte->getTarifA0(),
                $acte->getTarifA1(),
                $acte->getTarif(),
                $acte->getTarifB(),
                $acte->getTarifC(),
                $acte->getUnite(),
                $acte->getStatut(),
            ],
            $items,
        );
    }

    public function getById(int $id): ActeFinancier
    {
        $acte = $this->acteFinancierRepository->find($id);
        if (null === $acte) {
            throw new NotFoundException('Acte tarifaire non trouvé.');
        }

        return $acte;
    }

    /**
     * @return array{imported: int, updated: int, skipped: int, total: int}
     */
    public function importFromUpload(UploadedFile $file): array
    {
        $extension = strtolower((string) $file->getClientOriginalExtension());
        if (!in_array($extension, ['xlsx', 'xls'], true)) {
            throw new ConflictException('Envoyez un fichier Excel (.xlsx).');
        }

        return $this->grilleTarifaireImportService->importFromFile($file->getPathname());
    }

    /**
     * @return array{imported: int, updated: int, skipped: int, total: int}
     */
    public function importFromPath(string $path): array
    {
        return $this->grilleTarifaireImportService->importFromFile($path);
    }

    /** @return array<string, mixed> */
    public function serializeSummary(ActeFinancier $acte): array
    {
        return [
            'id' => $acte->getId(),
            'code' => $acte->getCode(),
            'libelle' => $acte->getLibelle(),
            'serviceGrille' => $acte->getServiceGrille(),
            'sousCategorie' => $acte->getSousCategorie(),
            'tarifA0' => $acte->getTarifA0(),
            'tarifA1' => $acte->getTarifA1(),
            'tarifA' => $acte->getTarif(),
            'tarifB' => $acte->getTarifB(),
            'tarifC' => $acte->getTarifC(),
            'unite' => $acte->getUnite(),
            'statut' => $acte->getStatut(),
            'createdAt' => $acte->getCreatedAt()?->format(\DateTimeInterface::ATOM),
        ];
    }

    /** @return array<string, mixed> */
    public function buildMeta(): array
    {
        return [
            'statuts' => [ActeFinancier::STATUT_ACTIF, ActeFinancier::STATUT_INACTIF],
            'unites' => [ActeFinancier::UNITE_FC],
            'serviceGrilles' => $this->acteFinancierRepository->listServiceGrilles(),
        ];
    }

    private function assertValid(object $value): void
    {
        $errors = $this->validator->validate($value);
        if (count($errors) > 0) {
            throw new ValidationFailedException($value, $errors);
        }
    }
}
