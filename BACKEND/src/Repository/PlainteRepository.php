<?php

namespace App\Repository;

use App\Entity\Plainte;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Plainte>
 */
class PlainteRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Plainte::class);
    }

    /**
     * @return array{items: list<Plainte>, total: int}
     */
    public function paginate(int $page, int $limit, ?string $search = null): array
    {
        $qb = $this->createQueryBuilder('p')
            ->orderBy('p.ordre', 'ASC')
            ->addOrderBy('p.libelle', 'ASC');

        $normalizedSearch = null !== $search ? trim($search) : '';
        if ('' !== $normalizedSearch) {
            $qb
                ->andWhere('LOWER(p.code) LIKE :search OR LOWER(p.libelle) LIKE :search')
                ->setParameter('search', '%' . mb_strtolower($normalizedSearch) . '%');
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

        return ['items' => $items, 'total' => $total];
    }

    /**
     * @return list<Plainte>
     */
    public function findActifs(): array
    {
        return $this->createQueryBuilder('p')
            ->andWhere('p.statut = :statut')
            ->setParameter('statut', Plainte::STATUT_ACTIF)
            ->orderBy('p.ordre', 'ASC')
            ->addOrderBy('p.libelle', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
