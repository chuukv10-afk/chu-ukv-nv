<?php

namespace App\Service\Referentiel;

use App\Entity\TypeExamen;
use App\Repository\TypeExamenRepository;
use App\Service\Support\AbstractCodeLibelleCrudService;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ObjectRepository;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/** @extends AbstractCodeLibelleCrudService<TypeExamen> */
final class TypeExamenService extends AbstractCodeLibelleCrudService
{
    public function __construct(
        EntityManagerInterface $eM,
        private readonly TypeExamenRepository $typeExamenRepository,
        ValidatorInterface $validator,
    ) {
        parent::__construct($eM, $validator);
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
