<?php

namespace App\Repository;

use App\Entity\Fournisseur;
use App\Entity\Reception;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Reception>
 */
class ReceptionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Reception::class);
    }

    /**
     * @return array{items: list<Reception>, total: int}
     */
    public function paginate(int $page, int $limit, ?string $search = null, ?string $statut = null): array
    {
        $qb = $this->createQueryBuilder('r')
            ->leftJoin('r.fournisseur', 'f')->addSelect('f')
            ->orderBy('r.createdAt', 'DESC');

        $normalizedSearch = null !== $search ? trim($search) : '';
        if ('' !== $normalizedSearch) {
            $qb
                ->andWhere('LOWER(r.numero) LIKE :search OR LOWER(f.libelle) LIKE :search OR LOWER(r.referenceExterne) LIKE :search')
                ->setParameter('search', '%' . mb_strtolower($normalizedSearch) . '%');
        }

        if (null !== $statut && '' !== $statut) {
            $qb->andWhere('r.statut = :statut')->setParameter('statut', $statut);
        }

        $countQb = clone $qb;
        $total = (int) $countQb->select('COUNT(r.id)')->resetDQLPart('orderBy')->getQuery()->getSingleScalarResult();

        $items = $qb
            ->setFirstResult(max(0, ($page - 1) * $limit))
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();

        return ['items' => $items, 'total' => $total];
    }

    public function countByFournisseur(Fournisseur $fournisseur): int
    {
        return (int) $this->createQueryBuilder('r')
            ->select('COUNT(r.id)')
            ->andWhere('r.fournisseur = :fournisseur')
            ->setParameter('fournisseur', $fournisseur)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function countNumeroPrefix(string $prefix): int
    {
        return (int) $this->createQueryBuilder('r')
            ->select('COUNT(r.id)')
            ->andWhere('r.numero LIKE :prefix')
            ->setParameter('prefix', $prefix . '%')
            ->getQuery()
            ->getSingleScalarResult();
    }
}
