<?php

namespace App\Repository;

use App\Entity\TypeAntecedent;
use App\Repository\Trait\CodeLibellePaginateTrait;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<TypeAntecedent>
 */
class TypeAntecedentRepository extends ServiceEntityRepository
{
    use CodeLibellePaginateTrait;

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, TypeAntecedent::class);
    }

    /**
     * @return array{items: list<TypeAntecedent>, total: int}
     */
    public function paginate(int $page, int $limit, ?string $search = null): array
    {
        return $this->paginateByCodeLibelle('t', $page, $limit, $search);
    }

    public function countAntecedents(int $typeAntecedentId): int
    {
        return (int) $this->getEntityManager()->createQueryBuilder()
            ->select('COUNT(a.id)')
            ->from('App\Entity\Antecedent', 'a')
            ->where('a.type = :typeId')
            ->setParameter('typeId', $typeAntecedentId)
            ->getQuery()
            ->getSingleScalarResult();
    }
}
