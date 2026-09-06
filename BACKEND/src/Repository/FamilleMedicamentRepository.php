<?php

namespace App\Repository;

use App\Entity\FamilleMedicament;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<FamilleMedicament>
 */
class FamilleMedicamentRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, FamilleMedicament::class);
    }

    /**
     * @return array{items: list<FamilleMedicament>, total: int}
     */
    public function paginate(int $page, int $limit, ?string $search = null): array
    {
        $qb = $this->createQueryBuilder('f')
            ->orderBy('f.ordre', 'ASC')
            ->addOrderBy('f.libelle', 'ASC');

        $normalizedSearch = null !== $search ? trim($search) : '';
        if ('' !== $normalizedSearch) {
            $qb
                ->andWhere('LOWER(f.code) LIKE :search OR LOWER(f.libelle) LIKE :search')
                ->setParameter('search', '%' . mb_strtolower($normalizedSearch) . '%');
        }

        $countQb = clone $qb;
        $total = (int) $countQb
            ->select('COUNT(f.id)')
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
     * @return list<FamilleMedicament>
     */
    public function findActifs(): array
    {
        return $this->createQueryBuilder('f')
            ->andWhere('f.statut = :statut')
            ->setParameter('statut', FamilleMedicament::STATUT_ACTIF)
            ->orderBy('f.ordre', 'ASC')
            ->addOrderBy('f.libelle', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
