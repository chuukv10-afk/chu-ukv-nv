<?php

namespace App\Repository;

use App\Entity\Chambre;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Chambre>
 */
class ChambreRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Chambre::class);
    }

    /**
     * @return array{items: list<Chambre>, total: int}
     */
    public function paginate(int $page, int $limit, ?string $search = null, ?string $type = null, ?int $blocId = null): array
    {
        $qb = $this->createQueryBuilder('c')
            ->leftJoin('c.bloc', 'b')
            ->addSelect('b')
            ->leftJoin('c.lits', 'l')
            ->addSelect('l')
            ->orderBy('c.libelle', 'ASC')
            ->distinct();

        $normalizedSearch = null !== $search ? trim($search) : '';
        if ('' !== $normalizedSearch) {
            $qb
                ->andWhere('LOWER(c.code) LIKE :search OR LOWER(c.libelle) LIKE :search OR LOWER(c.type) LIKE :search')
                ->setParameter('search', '%' . strtolower($normalizedSearch) . '%');
        }

        if (null !== $type && '' !== trim($type)) {
            $qb
                ->andWhere('c.type = :type')
                ->setParameter('type', strtoupper(trim($type)));
        }

        if (null !== $blocId) {
            $qb
                ->andWhere('b.id = :blocId')
                ->setParameter('blocId', $blocId);
        }

        $countQb = clone $qb;
        $total = (int) $countQb
            ->select('COUNT(DISTINCT c.id)')
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
     * @return list<Chambre>
     */
    public function findForExport(?string $search = null, ?string $type = null, ?int $blocId = null): array
    {
        $qb = $this->createQueryBuilder('c')
            ->leftJoin('c.bloc', 'b')
            ->addSelect('b')
            ->leftJoin('c.lits', 'l')
            ->addSelect('l')
            ->orderBy('c.libelle', 'ASC')
            ->distinct();

        $normalizedSearch = null !== $search ? trim($search) : '';
        if ('' !== $normalizedSearch) {
            $qb
                ->andWhere('LOWER(c.code) LIKE :search OR LOWER(c.libelle) LIKE :search OR LOWER(c.type) LIKE :search')
                ->setParameter('search', '%' . strtolower($normalizedSearch) . '%');
        }

        if (null !== $type && '' !== trim($type)) {
            $qb
                ->andWhere('c.type = :type')
                ->setParameter('type', strtoupper(trim($type)));
        }

        if (null !== $blocId) {
            $qb
                ->andWhere('b.id = :blocId')
                ->setParameter('blocId', $blocId);
        }

        return $qb->getQuery()->getResult();
    }
}
