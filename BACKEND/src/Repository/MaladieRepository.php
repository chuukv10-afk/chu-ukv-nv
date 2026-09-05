<?php

namespace App\Repository;

use App\Entity\Maladie;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Maladie>
 */
class MaladieRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Maladie::class);
    }

    /**
     * @return array{items: list<Maladie>, total: int}
     */
    public function paginate(int $page, int $limit, ?string $search = null, ?string $chapitre = null): array
    {
        $qb = $this->createQueryBuilder('m')
            ->leftJoin('m.dpi', 'a')
            ->leftJoin('m.diagnostics', 'd')
            ->addSelect('a', 'd')
            ->orderBy('m.code_cim10', 'ASC')
            ->distinct();

        $this->applyFilters($qb, $search, $chapitre);

        $countQb = clone $qb;
        $total = (int) $countQb
            ->select('COUNT(DISTINCT m.id)')
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
     * @return list<Maladie>
     */
    public function findForExport(?string $search = null, ?string $chapitre = null): array
    {
        $qb = $this->createQueryBuilder('m')
            ->orderBy('m.code_cim10', 'ASC');

        $this->applyFilters($qb, $search, $chapitre);

        return $qb->getQuery()->getResult();
    }

    /** @return list<string> */
    public function findDistinctChapitres(): array
    {
        $rows = $this->createQueryBuilder('m')
            ->select('DISTINCT m.chapitre AS chapitre')
            ->andWhere('m.chapitre IS NOT NULL')
            ->andWhere('m.chapitre != :empty')
            ->setParameter('empty', '')
            ->orderBy('chapitre', 'ASC')
            ->getQuery()
            ->getSingleColumnResult();

        return array_values(array_filter(array_map(
            static fn (mixed $value): string => is_string($value) ? trim($value) : '',
            $rows,
        )));
    }

    /** @return array<string, true> */
    public function findAllCodesIndexed(): array
    {
        $rows = $this->createQueryBuilder('m')
            ->select('m.code_cim10')
            ->getQuery()
            ->getSingleColumnResult();

        $indexed = [];
        foreach ($rows as $code) {
            if (is_string($code) && '' !== $code) {
                $indexed[strtoupper($code)] = true;
            }
        }

        return $indexed;
    }

    private function applyFilters(\Doctrine\ORM\QueryBuilder $qb, ?string $search, ?string $chapitre): void
    {
        $normalizedSearch = null !== $search ? trim($search) : '';
        if ('' !== $normalizedSearch) {
            $qb
                ->andWhere('LOWER(m.code_cim10) LIKE :search OR LOWER(m.libelle) LIKE :search')
                ->setParameter('search', '%' . mb_strtolower($normalizedSearch) . '%');
        }

        if (null !== $chapitre && '' !== trim($chapitre)) {
            $qb
                ->andWhere('m.chapitre = :chapitre')
                ->setParameter('chapitre', trim($chapitre));
        }
    }
}
