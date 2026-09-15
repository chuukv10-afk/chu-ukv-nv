<?php

namespace App\Repository;

use App\Entity\Structure;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Structure>
 */
class StructureRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Structure::class);
    }

    /**
     * @return array{items: list<Structure>, total: int}
     */
    public function paginate(int $page, int $limit, ?string $search = null, ?string $type = null, ?string $statut = null): array
    {
        $qb = $this->createQueryBuilder('s')
            ->orderBy('s.libelle', 'ASC');

        $normalizedSearch = null !== $search ? trim($search) : '';
        if ('' !== $normalizedSearch) {
            $qb
                ->andWhere('LOWER(s.code) LIKE :search OR LOWER(s.libelle) LIKE :search')
                ->setParameter('search', '%' . mb_strtolower($normalizedSearch) . '%');
        }

        if (null !== $type && '' !== trim($type)) {
            $qb->andWhere('s.type = :type')->setParameter('type', strtoupper(trim($type)));
        }

        if (null !== $statut && '' !== trim($statut)) {
            $qb->andWhere('s.statut = :statut')->setParameter('statut', strtoupper(trim($statut)));
        }

        $countQb = clone $qb;
        $total = (int) $countQb->select('COUNT(s.id)')->getQuery()->getSingleScalarResult();

        $items = $qb
            ->setFirstResult(max(0, ($page - 1) * $limit))
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();

        return ['items' => $items, 'total' => $total];
    }

    /**
     * @return list<Structure>
     */
    public function findActifs(?string $type = null): array
    {
        $qb = $this->createQueryBuilder('s')
            ->andWhere('s.statut = :statut')
            ->setParameter('statut', Structure::STATUT_ACTIF)
            ->orderBy('s.libelle', 'ASC');

        if (null !== $type && '' !== trim($type)) {
            $qb->andWhere('s.type = :type')->setParameter('type', strtoupper(trim($type)));
        }

        return $qb->getQuery()->getResult();
    }
}
