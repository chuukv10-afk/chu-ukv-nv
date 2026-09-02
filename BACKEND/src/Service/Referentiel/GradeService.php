<?php

namespace App\Service\Referentiel;

use App\Entity\Grade;
use App\Repository\GradeRepository;
use App\Service\Support\AbstractCodeLibelleCrudService;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ObjectRepository;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/** @extends AbstractCodeLibelleCrudService<Grade> */
final class GradeService extends AbstractCodeLibelleCrudService
{
    public function __construct(
        EntityManagerInterface $eM,
        private readonly GradeRepository $gradeRepository,
        ValidatorInterface $validator,
    ) {
        parent::__construct($eM, $validator);
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
