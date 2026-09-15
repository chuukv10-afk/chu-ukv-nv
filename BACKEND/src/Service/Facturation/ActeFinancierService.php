<?php

namespace App\Service\Facturation;

use App\DTO\Common\PaginatedResult;
use App\DTO\Facturation\FacturationListQuery;
use App\DTO\Facturation\UpsertActeFinancierInput;
use App\Entity\ActeFinancier;
use App\Entity\ActeFinancierVisite;
use App\Exception\ConflictException;
use App\Exception\NotFoundException;
use App\Repository\ActeFinancierRepository;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Validator\Exception\ValidationFailedException;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final class ActeFinancierService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly ActeFinancierRepository $acteFinancierRepository,
        private readonly GrilleTarifaireImportService $grilleTarifaireImportService,
        private readonly TarificationService $tarificationService,
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

    public function create(UpsertActeFinancierInput $input): ActeFinancier
    {
        $this->assertValid($input);
        $service = trim($input->serviceGrille);
        $libelle = trim($input->libelle);
        $this->assertUniqueServiceLibelle($service, $libelle, null);

        $acte = (new ActeFinancier())
            ->setCode($this->buildCode($service, $libelle))
            ->setCreatedAt(new \DateTimeImmutable())
            ->setUnite(ActeFinancier::UNITE_FC);
        $this->apply($acte, $input);

        $this->entityManager->persist($acte);
        try {
            $this->entityManager->flush();
        } catch (UniqueConstraintViolationException) {
            throw new ConflictException('Un acte avec ce service et ce libellé existe déjà.');
        }

        return $acte;
    }

    public function update(int $id, UpsertActeFinancierInput $input): ActeFinancier
    {
        $this->assertValid($input);
        $acte = $this->getById($id);
        $service = trim($input->serviceGrille);
        $libelle = trim($input->libelle);
        $this->assertUniqueServiceLibelle($service, $libelle, $acte->getId());
        $this->apply($acte, $input);

        try {
            $this->entityManager->flush();
        } catch (UniqueConstraintViolationException) {
            throw new ConflictException('Un acte avec ce service et ce libellé existe déjà.');
        }

        return $acte;
    }

    public function delete(int $id): void
    {
        $acte = $this->getById($id);
        if (ActeFinancier::CODE_CONSULTATION === $acte->getCode()) {
            throw new ConflictException('L\'acte consultation de référence ne peut pas être supprimé.');
        }

        $usageCount = (int) $this->entityManager->getRepository(ActeFinancierVisite::class)->count(['acte' => $acte]);
        if ($usageCount > 0) {
            throw new ConflictException('Cet acte est déjà utilisé sur des visites. Désactivez-le plutôt que de le supprimer.');
        }

        $this->entityManager->remove($acte);
        $this->entityManager->flush();
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
        $known = TarificationService::serviceGrilles();
        $existing = $this->acteFinancierRepository->listServiceGrilles();
        $serviceGrilles = array_values(array_unique(array_merge($known, $existing)));
        sort($serviceGrilles);

        return [
            'statuts' => ActeFinancier::getStatuts(),
            'unites' => [ActeFinancier::UNITE_FC],
            'serviceGrilles' => $serviceGrilles,
        ];
    }

    private function apply(ActeFinancier $acte, UpsertActeFinancierInput $input): void
    {
        $statut = strtoupper(trim($input->statut));
        if (!in_array($statut, ActeFinancier::getStatuts(), true)) {
            throw new ConflictException('Statut invalide.');
        }

        $sousCategorie = trim((string) $input->sousCategorie);

        $acte
            ->setServiceGrille(trim($input->serviceGrille))
            ->setSousCategorie('' === $sousCategorie ? null : $sousCategorie)
            ->setLibelle(trim($input->libelle))
            ->setTarifA0($this->money($input->tarifA0, 'A0'))
            ->setTarifA1($this->money($input->tarifA1, 'A1'))
            ->setTarif($this->money($input->tarifA, 'A'))
            ->setTarifB($this->money($input->tarifB, 'B'))
            ->setTarifC($this->money($input->tarifC, 'C'))
            ->setUnite(ActeFinancier::UNITE_FC)
            ->setStatut($statut);
    }

    private function assertUniqueServiceLibelle(string $service, string $libelle, ?int $excludeId): void
    {
        $existing = $this->acteFinancierRepository->findOneBy([
            'serviceGrille' => $service,
            'libelle' => $libelle,
        ]);
        if (null !== $existing && $existing->getId() !== $excludeId) {
            throw new ConflictException('Un acte avec ce service et ce libellé existe déjà.');
        }
    }

    private function buildCode(string $service, string $libelle): string
    {
        $prefix = $this->tarificationService->prefixForService($service);
        $hash = strtoupper(substr(sha1($service . '|' . $libelle), 0, 8));
        $code = $prefix . '-' . $hash;

        if (null === $this->acteFinancierRepository->findOneBy(['code' => $code])) {
            return $code;
        }

        return $prefix . '-' . strtoupper(substr(sha1($service . '|' . $libelle . '|' . uniqid('', true)), 0, 8));
    }

    private function money(string $value, string $label): string
    {
        $normalized = str_replace([' ', ','], ['', '.'], trim($value));
        if ('' === $normalized) {
            return '0.00';
        }
        if (!is_numeric($normalized) || (float) $normalized < 0) {
            throw new ConflictException(sprintf('Tarif %s invalide.', $label));
        }

        return number_format((float) $normalized, 2, '.', '');
    }

    private function assertValid(object $value): void
    {
        $errors = $this->validator->validate($value);
        if (count($errors) > 0) {
            throw new ValidationFailedException($value, $errors);
        }
    }
}
