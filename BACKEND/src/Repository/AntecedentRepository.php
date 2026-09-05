<?php

namespace App\Repository;

use App\Entity\Antecedent;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Antecedent>
 */
class AntecedentRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Antecedent::class);
    }

    /**
     * @return array{items: list<Antecedent>, total: int}
     */
    public function paginateByDpi(int $dpiId, int $page, int $limit, ?int $typeId = null): array
    {
        $qb = $this->createQueryBuilder('a')
            ->innerJoin('a.type', 't')
            ->innerJoin('a.maladie', 'm')
            ->addSelect('t', 'm')
            ->andWhere('a.dpi = :dpiId')
            ->setParameter('dpiId', $dpiId)
            ->orderBy('a.createdAt', 'DESC');

        if (null !== $typeId) {
            $qb
                ->andWhere('t.id = :typeId')
                ->setParameter('typeId', $typeId);
        }

        $countQb = clone $qb;
        $total = (int) $countQb
            ->select('COUNT(a.id)')
            ->getQuery()
            ->getSingleScalarResult();

        $items = $qb
            ->setFirstResult(max(0, ($page - 1) * $limit))
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();

        return ['items' => $items, 'total' => $total];
    }

    public function findDuplicate(int $dpiId, int $typeId, int $maladieId): ?Antecedent
    {
        return $this->createQueryBuilder('a')
            ->andWhere('a.dpi = :dpiId')
            ->andWhere('a.type = :typeId')
            ->andWhere('a.maladie = :maladieId')
            ->setParameter('dpiId', $dpiId)
            ->setParameter('typeId', $typeId)
            ->setParameter('maladieId', $maladieId)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
