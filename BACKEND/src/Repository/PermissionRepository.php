<?php

namespace App\Repository;

use App\Entity\Permission;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Permission>
 */
class PermissionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Permission::class);
    }

    /**
     * @return array{items: list<Permission>, total: int}
     */
    public function paginate(int $page, int $limit, ?string $module = null, ?string $search = null): array
    {
        $qb = $this->createQueryBuilder('p')
            ->orderBy('p.module', 'ASC')
            ->addOrderBy('p.code', 'ASC');

        if (null !== $module && '' !== trim($module)) {
            $qb
                ->andWhere('p.module = :module')
                ->setParameter('module', Permission::normalizeModule($module));
        }

        $normalizedSearch = null !== $search ? trim($search) : '';
        if ('' !== $normalizedSearch) {
            $qb
                ->andWhere('LOWER(p.code) LIKE :search OR LOWER(p.libelle) LIKE :search')
                ->setParameter('search', '%' . strtolower($normalizedSearch) . '%');
        }

        $countQb = clone $qb;
        $total = (int) $countQb
            ->select('COUNT(p.id)')
            ->getQuery()
            ->getSingleScalarResult();

        $items = $qb
            ->setFirstResult(max(0, ($page - 1) * $limit))
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();

        return [
            'items' => $items,
            'total' => $total,
        ];
    }
}
