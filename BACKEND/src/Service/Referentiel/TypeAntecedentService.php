<?php

namespace App\Service\Referentiel;

use App\Entity\TypeAntecedent;
use App\Exception\ConflictException;
use App\Repository\TypeAntecedentRepository;
use App\Service\Support\AbstractCodeLibelleCrudService;
use App\Service\Support\ReferentielPaginateTrait;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ObjectRepository;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/** @extends AbstractCodeLibelleCrudService<TypeAntecedent> */
final class TypeAntecedentService extends AbstractCodeLibelleCrudService
{
    use ReferentielPaginateTrait;

    public function __construct(
        EntityManagerInterface $eM,
        private readonly TypeAntecedentRepository $typeAntecedentRepository,
        ValidatorInterface $validator,
    ) {
        parent::__construct($eM, $validator);
    }

    public function delete(int $id): void
    {
        if ($this->typeAntecedentRepository->countAntecedents($id) > 0) {
            throw new ConflictException('Ce type d\'antécédent est encore utilisé et ne peut pas être supprimé.');
        }

        parent::delete($id);
    }

    public function serializeSummary(object $entity): array
    {
        /** @var TypeAntecedent $entity */
        return [
            ...parent::serializeSummary($entity),
            'antecedentCount' => $this->typeAntecedentRepository->countAntecedents((int) $entity->getId()),
        ];
    }

    protected function paginateEntities(int $page, int $limit, ?string $search): array
    {
        return $this->typeAntecedentRepository->paginate($page, $limit, $search);
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
