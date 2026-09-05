<?php

namespace App\Service\Referentiel;

use App\Entity\TypeExamen;
use App\Exception\ConflictException;
use App\DTO\Referentiel\ReferentielListQuery;
use App\Repository\TypeExamenRepository;
use App\Service\Support\AbstractCodeLibelleCrudService;
use App\Service\Support\ReferentielPaginateTrait;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ObjectRepository;
use Symfony\Component\Validator\Exception\ValidationFailedException;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/** @extends AbstractCodeLibelleCrudService<TypeExamen> */
final class TypeExamenService extends AbstractCodeLibelleCrudService
{
    use ReferentielPaginateTrait;

    public function __construct(
        EntityManagerInterface $eM,
        private readonly TypeExamenRepository $typeExamenRepository,
        ValidatorInterface $validator,
    ) {
        parent::__construct($eM, $validator);
    }

    public function delete(int $id): void
    {
        $typeExamen = $this->getById($id);
        if (!$typeExamen->getExamens()->isEmpty()) {
            throw new ConflictException('Cette catégorie contient encore des examens et ne peut pas être supprimée.');
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

        $items = $this->typeExamenRepository->findForExport($query->search);

        return array_map(
            fn (TypeExamen $typeExamen): array => $this->buildExportRow($typeExamen),
            $items,
        );
    }

    /**
     * @return list<string|null>
     */
    public function buildExportRow(TypeExamen $typeExamen): array
    {
        return [
            $typeExamen->getCode(),
            $typeExamen->getLibelle(),
            (string) $typeExamen->getExamens()->count(),
        ];
    }

    public function serializeSummary(object $entity): array
    {
        /** @var TypeExamen $entity */
        return [
            ...parent::serializeSummary($entity),
            'examenCount' => $entity->getExamens()->count(),
        ];
    }

    protected function paginateEntities(int $page, int $limit, ?string $search): array
    {
        return $this->typeExamenRepository->paginate($page, $limit, $search);
    }

    protected function repository(): ObjectRepository
    {
        return $this->typeExamenRepository;
    }

    protected function instantiate(): object
    {
        return new TypeExamen();
    }

    protected function duplicateCodeMessage(): string
    {
        return 'Ce code type d\'examen existe déjà.';
    }

    protected function notFoundMessage(): string
    {
        return 'Type d\'examen non trouvé.';
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
