<?php

namespace App\Repository;

use App\Entity\Departement;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Departement>
 */
class DepartementRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Departement::class);
    }

    /**
     * @return array{items: list<Departement>, total: int}
     */
    public function paginate(int $page, int $limit, ?string $search = null, ?string $type = null): array
    {
        $qb = $this->createQueryBuilder('d')
            ->leftJoin('d.services', 's')
            ->addSelect('s')
            ->orderBy('d.libelle', 'ASC')
            ->distinct();

        $normalizedSearch = null !== $search ? trim($search) : '';
        if ('' !== $normalizedSearch) {
            $qb
                ->andWhere('LOWER(d.code) LIKE :search OR LOWER(d.libelle) LIKE :search')
                ->setParameter('search', '%' . strtolower($normalizedSearch) . '%');
        }

        if (null !== $type && '' !== trim($type)) {
            $qb
                ->andWhere('d.type = :type')
                ->setParameter('type', strtoupper(trim($type)));
        }

        $countQb = clone $qb;
        $total = (int) $countQb
            ->select('COUNT(DISTINCT d.id)')
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
     * @return list<Departement>
     */
    public function findForExport(?string $search = null, ?string $type = null): array
    {
        $qb = $this->createQueryBuilder('d')
            ->leftJoin('d.services', 's')
            ->addSelect('s')
            ->orderBy('d.libelle', 'ASC')
            ->distinct();

        $normalizedSearch = null !== $search ? trim($search) : '';
        if ('' !== $normalizedSearch) {
            $qb
                ->andWhere('LOWER(d.code) LIKE :search OR LOWER(d.libelle) LIKE :search')
                ->setParameter('search', '%' . strtolower($normalizedSearch) . '%');
        }

        if (null !== $type && '' !== trim($type)) {
            $qb
                ->andWhere('d.type = :type')
                ->setParameter('type', strtoupper(trim($type)));
        }

        return $qb->getQuery()->getResult();
    }
}
