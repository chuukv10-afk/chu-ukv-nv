<?php

namespace App\Repository;

use App\Entity\Permission;
use App\Entity\Role;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Role>
 */
class RoleRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Role::class);
    }

    /**
     * @return array{items: list<Role>, total: int}
     */
    public function paginateAssignments(int $page, int $limit, ?string $search = null, ?string $module = null): array
    {
        $qb = $this->createQueryBuilder('r')
            ->leftJoin('r.permissions', 'p')
            ->addSelect('p')
            ->orderBy('r.code', 'ASC')
            ->distinct();

        $normalizedSearch = null !== $search ? trim($search) : '';
        if ('' !== $normalizedSearch) {
            $qb
                ->andWhere('LOWER(r.code) LIKE :search OR LOWER(r.libelle) LIKE :search')
                ->setParameter('search', '%' . strtolower($normalizedSearch) . '%');
        }

        if (null !== $module && '' !== trim($module)) {
            $qb
                ->andWhere('p.module = :module')
                ->setParameter('module', Permission::normalizeModule($module));
        }

        $countQb = clone $qb;
        $total = (int) $countQb
            ->select('COUNT(DISTINCT r.id)')
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
