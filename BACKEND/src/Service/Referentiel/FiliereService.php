<?php

namespace App\Service\Referentiel;

use App\Entity\Filiere;
use App\Exception\ConflictException;
use App\Repository\FiliereRepository;
use App\Service\Support\AbstractCodeLibelleCrudService;
use App\Service\Support\ReferentielPaginateTrait;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ObjectRepository;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/** @extends AbstractCodeLibelleCrudService<Filiere> */
final class FiliereService extends AbstractCodeLibelleCrudService
{
    use ReferentielPaginateTrait;

    public function __construct(
        EntityManagerInterface $eM,
        private readonly FiliereRepository $filiereRepository,
        ValidatorInterface $validator,
    ) {
        parent::__construct($eM, $validator);
    }

    public function delete(int $id): void
    {
        $filiere = $this->getById($id);
        if (!$filiere->getCertificats()->isEmpty()) {
            throw new ConflictException('Cette filière est encore liée à des certificats d\'aptitude et ne peut pas être supprimée.');
        }

        parent::delete($id);
    }

    public function serializeSummary(object $entity): array
    {
        /** @var Filiere $entity */
        return [
            ...parent::serializeSummary($entity),
            'certificatCount' => $entity->getCertificats()->count(),
        ];
    }

    /**
     * @return list<array{id: int, code: string, libelle: string}>
     */
    public function listLookup(): array
    {
        return array_map(static fn (Filiere $filiere): array => [
            'id' => (int) $filiere->getId(),
            'code' => (string) $filiere->getCode(),
            'libelle' => (string) $filiere->getLibelle(),
        ], $this->filiereRepository->findAllOrdered());
    }

    protected function paginateEntities(int $page, int $limit, ?string $search): array
    {
        return $this->filiereRepository->paginate($page, $limit, $search);
    }

    protected function repository(): ObjectRepository
    {
        return $this->filiereRepository;
    }

    protected function instantiate(): object
    {
        return new Filiere();
    }

    protected function duplicateCodeMessage(): string
    {
        return 'Ce code filière existe déjà.';
    }

    protected function notFoundMessage(): string
    {
        return 'Filière non trouvée.';
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
