<?php

namespace App\Service\Clinique;

use App\Entity\Examen;
use App\Repository\ExamenRepository;
use App\Service\Support\AbstractCodeLibelleCrudService;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ObjectRepository;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/** @extends AbstractCodeLibelleCrudService<Examen> */
final class ExamenService extends AbstractCodeLibelleCrudService
{
    public function __construct(
        EntityManagerInterface $eM,
        private readonly ExamenRepository $examenRepository,
        ValidatorInterface $validator,
    ) {
        parent::__construct($eM, $validator);
    }

    protected function repository(): ObjectRepository
    {
        return $this->examenRepository;
    }

    protected function instantiate(): object
    {
        return new Examen();
    }

    protected function duplicateCodeMessage(): string
    {
        return 'Ce code examen existe déjà.';
    }

    protected function notFoundMessage(): string
    {
        return 'Examen non trouvé.';
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
