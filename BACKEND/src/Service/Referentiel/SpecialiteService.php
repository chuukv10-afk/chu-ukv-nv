<?php

namespace App\Service\Referentiel;

use App\Entity\Specialite;
use App\Repository\SpecialiteRepository;
use App\Service\Support\AbstractCodeLibelleCrudService;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ObjectRepository;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/** @extends AbstractCodeLibelleCrudService<Specialite> */
final class SpecialiteService extends AbstractCodeLibelleCrudService
{
    public function __construct(
        EntityManagerInterface $eM,
        private readonly SpecialiteRepository $specialiteRepository,
        ValidatorInterface $validator,
    ) {
        parent::__construct($eM, $validator);
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
