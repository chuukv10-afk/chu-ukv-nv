<?php

namespace App\Repository;

use App\Entity\Vente;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Vente>
 */
class VenteRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Vente::class);
    }

    /**
     * @return array{items: list<Vente>, total: int}
     */
    public function paginate(
        int $page,
        int $limit,
        ?string $search = null,
        ?string $statut = null,
        ?string $dateFrom = null,
        ?string $dateTo = null,
    ): array {
        $qb = $this->createQueryBuilder('v')
            ->leftJoin('v.patient', 'p')->addSelect('p')
            ->orderBy('v.createdAt', 'DESC');

        $normalizedSearch = null !== $search ? trim($search) : '';
        if ('' !== $normalizedSearch) {
            $qb
                ->andWhere('LOWER(v.numero) LIKE :search OR LOWER(v.clientNom) LIKE :search OR LOWER(p.nom) LIKE :search OR LOWER(p.postNom) LIKE :search')
                ->setParameter('search', '%' . mb_strtolower($normalizedSearch) . '%');
        }

        if (null !== $statut && '' !== $statut) {
            $qb->andWhere('v.statut = :statut')->setParameter('statut', $statut);
        }

        if (null !== $dateFrom && '' !== $dateFrom) {
            $qb
                ->andWhere('COALESCE(v.dateVente, v.createdAt) >= :dateFrom')
                ->setParameter('dateFrom', new \DateTimeImmutable($dateFrom . ' 00:00:00'));
        }
        if (null !== $dateTo && '' !== $dateTo) {
            $qb
                ->andWhere('COALESCE(v.dateVente, v.createdAt) <= :dateTo')
                ->setParameter('dateTo', new \DateTimeImmutable($dateTo . ' 23:59:59'));
        }

        $countQb = clone $qb;
        $total = (int) $countQb->select('COUNT(v.id)')->resetDQLPart('orderBy')->getQuery()->getSingleScalarResult();

        $items = $qb
            ->setFirstResult(max(0, ($page - 1) * $limit))
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();

        return ['items' => $items, 'total' => $total];
    }

    /**
     * @return list<Vente>
     */
    public function findForRecettes(?string $dateFrom, ?string $dateTo, ?string $search = null): array
    {
        $qb = $this->createQueryBuilder('v')
            ->leftJoin('v.patient', 'p')->addSelect('p')
            ->leftJoin('v.visite', 'vi')->addSelect('vi')
            ->leftJoin('vi.service', 's')->addSelect('s')
            ->andWhere('v.statut = :statut')
            ->setParameter('statut', Vente::STATUT_VALIDEE);

        if (null !== $dateFrom && '' !== $dateFrom) {
            $qb
                ->andWhere('COALESCE(v.dateVente, v.createdAt) >= :dateFrom')
                ->setParameter('dateFrom', new \DateTimeImmutable($dateFrom . ' 00:00:00'));
        }
        if (null !== $dateTo && '' !== $dateTo) {
            $qb
                ->andWhere('COALESCE(v.dateVente, v.createdAt) <= :dateTo')
                ->setParameter('dateTo', new \DateTimeImmutable($dateTo . ' 23:59:59'));
        }

        $normalizedSearch = null !== $search ? trim($search) : '';
        if ('' !== $normalizedSearch) {
            $qb
                ->andWhere('LOWER(v.numero) LIKE :search OR LOWER(v.clientNom) LIKE :search OR LOWER(p.nom) LIKE :search OR LOWER(p.postNom) LIKE :search OR LOWER(p.prenom) LIKE :search')
                ->setParameter('search', '%' . mb_strtolower($normalizedSearch) . '%');
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * @return list<Vente>
     */
    public function findValideesForStats(?string $dateFrom, ?string $dateTo): array
    {
        $qb = $this->createQueryBuilder('v')
            ->andWhere('v.statut = :statut')
            ->setParameter('statut', Vente::STATUT_VALIDEE);

        if (null !== $dateFrom && '' !== $dateFrom) {
            $qb
                ->andWhere('v.createdAt >= :from OR v.dateVente >= :from')
                ->setParameter('from', new \DateTimeImmutable($dateFrom . ' 00:00:00'));
        }
        if (null !== $dateTo && '' !== $dateTo) {
            $qb
                ->andWhere('v.createdAt <= :to OR v.dateVente <= :to')
                ->setParameter('to', new \DateTimeImmutable($dateTo . ' 23:59:59'));
        }

        return $qb->getQuery()->getResult();
    }

    public function countNumeroPrefix(string $prefix): int
    {
        return (int) $this->createQueryBuilder('v')
            ->select('COUNT(v.id)')
            ->andWhere('v.numero LIKE :prefix')
            ->setParameter('prefix', $prefix . '%')
            ->getQuery()
            ->getSingleScalarResult();
    }
}
