<?php

namespace App\Repository;

use App\Entity\Bloc;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Bloc>
 */
class BlocRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Bloc::class);
    }

    /**
     * @return array{items: list<Bloc>, total: int}
     */
    public function paginate(int $page, int $limit, ?string $search = null): array
    {
        $qb = $this->createQueryBuilder('b')
            ->leftJoin('b.chambres', 'c')
            ->addSelect('c')
            ->orderBy('b.libelle', 'ASC')
            ->distinct();

        $normalizedSearch = null !== $search ? trim($search) : '';
        if ('' !== $normalizedSearch) {
            $qb
                ->andWhere('LOWER(b.code) LIKE :search OR LOWER(b.libelle) LIKE :search')
                ->setParameter('search', '%' . strtolower($normalizedSearch) . '%');
        }

        $countQb = clone $qb;
        $total = (int) $countQb
            ->select('COUNT(DISTINCT b.id)')
            ->getQuery()
            ->getSingleScalarResult();

        $items = $qb
            ->setFirstResult(max(0, ($page - 1) * $limit))
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();

        return ['items' => $items, 'total' => $total];
    }

    /**
     * @return list<Bloc>
     */
    public function findForExport(?string $search = null): array
    {
        $qb = $this->createQueryBuilder('b')
            ->leftJoin('b.chambres', 'c')
            ->addSelect('c')
            ->orderBy('b.libelle', 'ASC')
            ->distinct();

        $normalizedSearch = null !== $search ? trim($search) : '';
        if ('' !== $normalizedSearch) {
            $qb
                ->andWhere('LOWER(b.code) LIKE :search OR LOWER(b.libelle) LIKE :search')
                ->setParameter('search', '%' . strtolower($normalizedSearch) . '%');
        }

        return $qb->getQuery()->getResult();
    }
}
