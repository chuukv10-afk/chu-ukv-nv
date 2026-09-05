<?php

namespace App\Repository\Trait;

trait CodeLibellePaginateTrait
{
    /**
     * @return array{items: list<object>, total: int}
     */
    protected function paginateByCodeLibelle(string $alias, int $page, int $limit, ?string $search = null): array
    {
        $qb = $this->createQueryBuilder($alias)
            ->orderBy("{$alias}.libelle", 'ASC');

        $normalizedSearch = null !== $search ? trim($search) : '';
        if ('' !== $normalizedSearch) {
            $qb
                ->andWhere("LOWER({$alias}.code) LIKE :search OR LOWER({$alias}.libelle) LIKE :search")
                ->setParameter('search', '%' . strtolower($normalizedSearch) . '%');
        }

        $countQb = clone $qb;
        $total = (int) $countQb
            ->select("COUNT({$alias}.id)")
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
     * @return list<object>
     */
    protected function findAllByCodeLibelle(string $alias, ?string $search = null): array
    {
        $qb = $this->createQueryBuilder($alias)
            ->orderBy("{$alias}.libelle", 'ASC');

        $normalizedSearch = null !== $search ? trim($search) : '';
        if ('' !== $normalizedSearch) {
            $qb
                ->andWhere("LOWER({$alias}.code) LIKE :search OR LOWER({$alias}.libelle) LIKE :search")
                ->setParameter('search', '%' . strtolower($normalizedSearch) . '%');
        }

        return $qb->getQuery()->getResult();
    }
}
