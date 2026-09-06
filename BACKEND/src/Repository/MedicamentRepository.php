<?php

namespace App\Repository;

use App\Entity\FamilleMedicament;
use App\Entity\Medicament;
use App\Entity\UniteMedicament;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Medicament>
 */
class MedicamentRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Medicament::class);
    }

    /**
     * @return array{items: list<Medicament>, total: int}
     */
    public function paginate(int $page, int $limit, ?string $search = null): array
    {
        $qb = $this->createQueryBuilder('m')
            ->leftJoin('m.unite', 'u')->addSelect('u')
            ->leftJoin('m.famille', 'f')->addSelect('f')
            ->orderBy('m.libelle', 'ASC');

        $normalizedSearch = null !== $search ? trim($search) : '';
        if ('' !== $normalizedSearch) {
            $qb
                ->andWhere('LOWER(m.code) LIKE :search OR LOWER(m.libelle) LIKE :search OR LOWER(m.dci) LIKE :search')
                ->setParameter('search', '%' . mb_strtolower($normalizedSearch) . '%');
        }

        $countQb = clone $qb;
        $total = (int) $countQb
            ->select('COUNT(m.id)')
            ->resetDQLPart('orderBy')
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
     * @return list<Medicament>
     */
    public function findForExport(?string $search = null): array
    {
        $qb = $this->createQueryBuilder('m')
            ->leftJoin('m.unite', 'u')->addSelect('u')
            ->leftJoin('m.famille', 'f')->addSelect('f')
            ->orderBy('m.libelle', 'ASC');

        $normalizedSearch = null !== $search ? trim($search) : '';
        if ('' !== $normalizedSearch) {
            $qb
                ->andWhere('LOWER(m.code) LIKE :search OR LOWER(m.libelle) LIKE :search OR LOWER(m.dci) LIKE :search')
                ->setParameter('search', '%' . mb_strtolower($normalizedSearch) . '%');
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * @return list<Medicament>
     */
    public function countActifs(): int
    {
        return (int) $this->createQueryBuilder('m')
            ->select('COUNT(m.id)')
            ->andWhere('m.statut = :statut')
            ->setParameter('statut', Medicament::STATUT_ACTIF)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function findActifs(): array
    {
        return $this->createQueryBuilder('m')
            ->leftJoin('m.unite', 'u')->addSelect('u')
            ->leftJoin('m.famille', 'f')->addSelect('f')
            ->andWhere('m.statut = :statut')
            ->setParameter('statut', Medicament::STATUT_ACTIF)
            ->orderBy('m.libelle', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function countByUnite(UniteMedicament $unite): int
    {
        return (int) $this->createQueryBuilder('m')
            ->select('COUNT(m.id)')
            ->andWhere('m.unite = :unite')
            ->setParameter('unite', $unite)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function countByFamille(FamilleMedicament $famille): int
    {
        return (int) $this->createQueryBuilder('m')
            ->select('COUNT(m.id)')
            ->andWhere('m.famille = :famille')
            ->setParameter('famille', $famille)
            ->getQuery()
            ->getSingleScalarResult();
    }
}
