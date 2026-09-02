<?php

namespace App\Service\Referentiel;

use App\Entity\TypeAntecedent;
use App\Repository\TypeAntecedentRepository;
use App\Service\Support\AbstractCodeLibelleCrudService;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ObjectRepository;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/** @extends AbstractCodeLibelleCrudService<TypeAntecedent> */
final class TypeAntecedentService extends AbstractCodeLibelleCrudService
{
    public function __construct(
        EntityManagerInterface $eM,
        private readonly TypeAntecedentRepository $typeAntecedentRepository,
        ValidatorInterface $validator,
    ) {
        parent::__construct($eM, $validator);
    }

    protected function repository(): ObjectRepository
    {
        return $this->typeAntecedentRepository;
    }

    protected function instantiate(): object
    {
        return new TypeAntecedent();
    }

    protected function duplicateCodeMessage(): string
    {
        return 'Ce code type d\'antécédent existe déjà.';
    }

    protected function notFoundMessage(): string
    {
        return 'Type d\'antécédent non trouvé.';
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
