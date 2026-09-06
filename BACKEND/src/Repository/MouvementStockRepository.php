<?php

namespace App\Repository;

use App\Entity\MouvementStock;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<MouvementStock>
 */
class MouvementStockRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, MouvementStock::class);
    }

    /**
     * @return array{items: list<MouvementStock>, total: int}
     */
    public function paginate(
        int $page,
        int $limit,
        ?string $search = null,
        ?int $medicamentId = null,
        ?string $dateFrom = null,
        ?string $dateTo = null,
    ): array {
        $qb = $this->createQueryBuilder('mv')
            ->leftJoin('mv.lot', 'l')->addSelect('l')
            ->leftJoin('l.medicament', 'm')->addSelect('m')
            ->orderBy('mv.createdAt', 'DESC');

        $normalizedSearch = null !== $search ? trim($search) : '';
        if ('' !== $normalizedSearch) {
            $qb
                ->andWhere('LOWER(mv.type) LIKE :search OR LOWER(l.numeroLot) LIKE :search OR LOWER(m.libelle) LIKE :search OR LOWER(m.code) LIKE :search')
                ->setParameter('search', '%' . mb_strtolower($normalizedSearch) . '%');
        }

        $this->applyMedicamentFilter($qb, $medicamentId);
        $this->applyPeriod($qb, $dateFrom, $dateTo);

        $countQb = clone $qb;
        $total = (int) $countQb->select('COUNT(mv.id)')->resetDQLPart('orderBy')->getQuery()->getSingleScalarResult();

        $items = $qb
            ->setFirstResult(max(0, ($page - 1) * $limit))
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();

        return ['items' => $items, 'total' => $total];
    }

    /**
     * @return list<MouvementStock>
     */
    public function findForFiche(?int $medicamentId, ?string $dateFrom, ?string $dateTo): array
    {
        $qb = $this->createQueryBuilder('mv')
            ->leftJoin('mv.lot', 'l')->addSelect('l')
            ->leftJoin('l.medicament', 'm')->addSelect('m')
            ->leftJoin('m.unite', 'u')->addSelect('u')
            ->leftJoin('m.famille', 'f')->addSelect('f')
            ->orderBy('m.libelle', 'ASC')
            ->addOrderBy('mv.createdAt', 'ASC')
            ->addOrderBy('mv.id', 'ASC');

        $this->applyMedicamentFilter($qb, $medicamentId);
        $this->applyPeriod($qb, $dateFrom, $dateTo);

        return $qb->getQuery()->getResult();
    }

    public function signedStockBefore(int $medicamentId, string $dateFrom): int
    {
        $entrees = (int) $this->createQueryBuilder('mv')
            ->select('COALESCE(SUM(mv.quantite), 0)')
            ->innerJoin('mv.lot', 'l')
            ->andWhere('l.medicament = :medicamentId')
            ->andWhere('mv.sens = :sens')
            ->andWhere('mv.createdAt < :before')
            ->setParameter('medicamentId', $medicamentId)
            ->setParameter('sens', MouvementStock::SENS_ENTREE)
            ->setParameter('before', new \DateTimeImmutable($dateFrom . ' 00:00:00'))
            ->getQuery()
            ->getSingleScalarResult();

        $sorties = (int) $this->createQueryBuilder('mv')
            ->select('COALESCE(SUM(mv.quantite), 0)')
            ->innerJoin('mv.lot', 'l')
            ->andWhere('l.medicament = :medicamentId')
            ->andWhere('mv.sens = :sens')
            ->andWhere('mv.createdAt < :before')
            ->setParameter('medicamentId', $medicamentId)
            ->setParameter('sens', MouvementStock::SENS_SORTIE)
            ->setParameter('before', new \DateTimeImmutable($dateFrom . ' 00:00:00'))
            ->getQuery()
            ->getSingleScalarResult();

        return $entrees - $sorties;
    }

    /**
     * @return array<int, int>
     */
    public function signedStockByMedicamentBefore(string $dateFrom): array
    {
        $rows = $this->createQueryBuilder('mv')
            ->select('IDENTITY(l.medicament) AS medicamentId, mv.sens AS sens, COALESCE(SUM(mv.quantite), 0) AS qte')
            ->innerJoin('mv.lot', 'l')
            ->andWhere('mv.createdAt < :before')
            ->setParameter('before', new \DateTimeImmutable($dateFrom . ' 00:00:00'))
            ->groupBy('l.medicament')
            ->addGroupBy('mv.sens')
            ->getQuery()
            ->getScalarResult();

        $totals = [];
        foreach ($rows as $row) {
            $id = (int) $row['medicamentId'];
            $qty = (int) $row['qte'];
            $totals[$id] = ($totals[$id] ?? 0) + (MouvementStock::SENS_ENTREE === $row['sens'] ? $qty : -$qty);
        }

        return $totals;
    }

    /**
     * @param list<string> $types
     * @return list<array{jour: string, qte: string}>
     */
    public function sumQuantiteByDayAndSens(?string $dateFrom, ?string $dateTo, string $sens, array $types = []): array
    {
        $qb = $this->createQueryBuilder('mv')
            ->select('SUBSTRING(mv.createdAt, 1, 10) AS jour, COALESCE(SUM(mv.quantite), 0) AS qte')
            ->andWhere('mv.sens = :sens')
            ->setParameter('sens', $sens)
            ->groupBy('jour')
            ->orderBy('jour', 'ASC');

        $this->applyPeriod($qb, $dateFrom, $dateTo);
        if ([] !== $types) {
            $qb->andWhere('mv.type IN (:types)')->setParameter('types', $types);
        }

        return $qb->getQuery()->getScalarResult();
    }

    /**
     * @param list<string> $types
     * @return list<array{category: string, count: string}>
     */
    public function sumSortiesByFamille(?string $dateFrom, ?string $dateTo, array $types): array
    {
        $qb = $this->createQueryBuilder('mv')
            ->select('COALESCE(f.libelle, :autre) AS category, COALESCE(SUM(mv.quantite), 0) AS count')
            ->innerJoin('mv.lot', 'l')
            ->innerJoin('l.medicament', 'm')
            ->leftJoin('m.famille', 'f')
            ->andWhere('mv.sens = :sens')
            ->andWhere('mv.type IN (:types)')
            ->setParameter('sens', MouvementStock::SENS_SORTIE)
            ->setParameter('types', $types)
            ->setParameter('autre', 'Autre')
            ->groupBy('f.id, f.libelle')
            ->orderBy('count', 'DESC');

        $this->applyPeriod($qb, $dateFrom, $dateTo);

        return $qb->getQuery()->getScalarResult();
    }

    /**
     * @param list<string> $types
     * @return list<array{id: string, name: string, count: string}>
     */
    public function topSortiesByMedicament(?string $dateFrom, ?string $dateTo, array $types, int $limit = 10): array
    {
        $qb = $this->createQueryBuilder('mv')
            ->select('m.id AS id, m.libelle AS name, COALESCE(SUM(mv.quantite), 0) AS count')
            ->innerJoin('mv.lot', 'l')
            ->innerJoin('l.medicament', 'm')
            ->andWhere('mv.sens = :sens')
            ->andWhere('mv.type IN (:types)')
            ->setParameter('sens', MouvementStock::SENS_SORTIE)
            ->setParameter('types', $types)
            ->groupBy('m.id, m.libelle')
            ->orderBy('count', 'DESC')
            ->setMaxResults($limit);

        $this->applyPeriod($qb, $dateFrom, $dateTo);

        return $qb->getQuery()->getScalarResult();
    }

    private function applyMedicamentFilter(\Doctrine\ORM\QueryBuilder $qb, ?int $medicamentId): void
    {
        if (null !== $medicamentId && $medicamentId > 0) {
            $qb->andWhere('m.id = :medicamentId')->setParameter('medicamentId', $medicamentId);
        }
    }

    private function applyPeriod(\Doctrine\ORM\QueryBuilder $qb, ?string $dateFrom, ?string $dateTo): void
    {
        if (null !== $dateFrom && '' !== $dateFrom) {
            $qb
                ->andWhere('mv.createdAt >= :dateFrom')
                ->setParameter('dateFrom', new \DateTimeImmutable($dateFrom . ' 00:00:00'));
        }
        if (null !== $dateTo && '' !== $dateTo) {
            $qb
                ->andWhere('mv.createdAt <= :dateTo')
                ->setParameter('dateTo', new \DateTimeImmutable($dateTo . ' 23:59:59'));
        }
    }
}
