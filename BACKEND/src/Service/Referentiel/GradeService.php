<?php

namespace App\Service\Referentiel;

use App\Entity\Grade;
use App\Exception\ConflictException;
use App\Repository\GradeRepository;
use App\Service\Support\AbstractCodeLibelleCrudService;
use App\Service\Support\ReferentielPaginateTrait;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ObjectRepository;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/** @extends AbstractCodeLibelleCrudService<Grade> */
final class GradeService extends AbstractCodeLibelleCrudService
{
    use ReferentielPaginateTrait;

    public function __construct(
        EntityManagerInterface $eM,
        private readonly GradeRepository $gradeRepository,
        ValidatorInterface $validator,
    ) {
        parent::__construct($eM, $validator);
    }

    public function delete(int $id): void
    {
        $grade = $this->getById($id);
        if (!$grade->getPersonnels()->isEmpty()) {
            throw new ConflictException('Ce grade est encore affecté à du personnel et ne peut pas être supprimé.');
        }

        parent::delete($id);
    }

    public function serializeSummary(object $entity): array
    {
        /** @var Grade $entity */
        return [
            ...parent::serializeSummary($entity),
            'personnelCount' => $entity->getPersonnels()->count(),
        ];
    }

    protected function paginateEntities(int $page, int $limit, ?string $search): array
    {
        return $this->gradeRepository->paginate($page, $limit, $search);
    }

    protected function repository(): ObjectRepository
    {
        return $this->gradeRepository;
    }

    protected function instantiate(): object
    {
        return new Grade();
    }

    protected function duplicateCodeMessage(): string
    {
        return 'Ce code grade existe déjà.';
    }

    protected function notFoundMessage(): string
    {
        return 'Grade non trouvé.';
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
