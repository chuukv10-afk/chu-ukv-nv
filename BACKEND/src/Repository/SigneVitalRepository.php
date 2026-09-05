<?php

namespace App\Repository;

use App\Entity\SigneVital;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<SigneVital>
 */
class SigneVitalRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, SigneVital::class);
    }

    /**
     * @return array{items: list<SigneVital>, total: int}
     */
    public function paginate(int $page, int $limit, ?string $search = null): array
    {
        $qb = $this->createQueryBuilder('s')
            ->orderBy('s.ordre', 'ASC')
            ->addOrderBy('s.libelle', 'ASC');

        $normalizedSearch = null !== $search ? trim($search) : '';
        if ('' !== $normalizedSearch) {
            $qb
                ->andWhere('LOWER(s.code) LIKE :search OR LOWER(s.libelle) LIKE :search')
                ->setParameter('search', '%' . mb_strtolower($normalizedSearch) . '%');
        }

        $countQb = clone $qb;
        $total = (int) $countQb
            ->select('COUNT(s.id)')
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
     * @return list<SigneVital>
     */
    public function findForTriage(): array
    {
        return $this->createQueryBuilder('s')
            ->andWhere('s.demandeAuTriage = :demande')
            ->andWhere('s.statut = :statut')
            ->setParameter('demande', true)
            ->setParameter('statut', SigneVital::STATUT_ACTIF)
            ->orderBy('s.ordre', 'ASC')
            ->addOrderBy('s.libelle', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return list<SigneVital>
     */
    public function findActifs(): array
    {
        return $this->createQueryBuilder('s')
            ->andWhere('s.statut = :statut')
            ->setParameter('statut', SigneVital::STATUT_ACTIF)
            ->orderBy('s.ordre', 'ASC')
            ->addOrderBy('s.libelle', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
