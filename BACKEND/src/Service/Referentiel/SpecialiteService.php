<?php

namespace App\Service\Referentiel;

use App\Entity\Specialite;
use App\Exception\ConflictException;
use App\DTO\Referentiel\ReferentielListQuery;
use App\Repository\SpecialiteRepository;
use App\Service\Support\AbstractCodeLibelleCrudService;
use App\Service\Support\ReferentielPaginateTrait;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ObjectRepository;
use Symfony\Component\Validator\Exception\ValidationFailedException;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/** @extends AbstractCodeLibelleCrudService<Specialite> */
final class SpecialiteService extends AbstractCodeLibelleCrudService
{
    use ReferentielPaginateTrait;

    public function __construct(
        EntityManagerInterface $eM,
        private readonly SpecialiteRepository $specialiteRepository,
        ValidatorInterface $validator,
    ) {
        parent::__construct($eM, $validator);
    }

    public function delete(int $id): void
    {
        $specialite = $this->getById($id);
        if (!$specialite->getPersonnels()->isEmpty()) {
            throw new ConflictException('Cette spécialité est encore affectée à du personnel et ne peut pas être supprimée.');
        }

        parent::delete($id);
    }

    /**
     * @return list<list<string|null>>
     */
    public function buildExportRows(ReferentielListQuery $query): array
    {
        $errors = $this->validator->validate($query);
        if (count($errors) > 0) {
            throw new ValidationFailedException($query, $errors);
        }

        $items = $this->specialiteRepository->findForExport($query->search);

        return array_map(
            fn (Specialite $specialite): array => $this->buildExportRow($specialite),
            $items,
        );
    }

    /**
     * @return list<string|null>
     */
    public function buildExportRow(Specialite $specialite): array
    {
        return [
            $specialite->getCode(),
            $specialite->getLibelle(),
            (string) $specialite->getPersonnels()->count(),
        ];
    }

    public function serializeSummary(object $entity): array
    {
        /** @var Specialite $entity */
        return [
            ...parent::serializeSummary($entity),
            'personnelCount' => $entity->getPersonnels()->count(),
        ];
    }

    protected function paginateEntities(int $page, int $limit, ?string $search): array
    {
        return $this->specialiteRepository->paginate($page, $limit, $search);
    }

    protected function repository(): ObjectRepository
    {
        return $this->specialiteRepository;
    }

    protected function instantiate(): object
    {
        return new Specialite();
    }

    protected function duplicateCodeMessage(): string
    {
        return 'Ce code spécialité existe déjà.';
    }

    protected function notFoundMessage(): string
    {
        return 'Spécialité non trouvée.';
    }

    protected function setCode(object $entity, string $code): void
    {
        $entity->setCode($code);
    }

    protected function setLibelle(object $entity, string $libelle): void
    {
        $entity->setLibelle($libelle);
    }

    protected function setCreatedAt(object $entity, \DateTimeImmutable $createdAt): void
    {
        $entity->setCreatedAt($createdAt);
    }
}
