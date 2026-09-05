<?php

namespace App\Repository;

use App\Entity\Lit;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Lit>
 */
class LitRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Lit::class);
    }

    /**
     * @return array{items: list<Lit>, total: int}
     */
    public function paginate(int $page, int $limit, ?string $search = null, ?int $chambreId = null): array
    {
        $qb = $this->createQueryBuilder('l')
            ->leftJoin('l.chambre', 'c')
            ->addSelect('c')
            ->leftJoin('c.bloc', 'b')
            ->addSelect('b')
            ->leftJoin('l.visites', 'v')
            ->orderBy('l.numeroLit', 'ASC')
            ->distinct();

        $normalizedSearch = null !== $search ? trim($search) : '';
        if ('' !== $normalizedSearch) {
            $qb
                ->andWhere('LOWER(l.code) LIKE :search OR LOWER(l.numeroLit) LIKE :search')
                ->setParameter('search', '%' . strtolower($normalizedSearch) . '%');
        }

        if (null !== $chambreId) {
            $qb
                ->andWhere('c.id = :chambreId')
                ->setParameter('chambreId', $chambreId);
        }

        $countQb = clone $qb;
        $total = (int) $countQb
            ->select('COUNT(DISTINCT l.id)')
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
     * @return list<Lit>
     */
    public function findForExport(?string $search = null, ?int $chambreId = null): array
    {
        $qb = $this->createQueryBuilder('l')
            ->leftJoin('l.chambre', 'c')
            ->addSelect('c')
            ->leftJoin('c.bloc', 'b')
            ->addSelect('b')
            ->leftJoin('l.visites', 'v')
            ->orderBy('l.numeroLit', 'ASC')
            ->distinct();

        $normalizedSearch = null !== $search ? trim($search) : '';
        if ('' !== $normalizedSearch) {
            $qb
                ->andWhere('LOWER(l.code) LIKE :search OR LOWER(l.numeroLit) LIKE :search')
                ->setParameter('search', '%' . strtolower($normalizedSearch) . '%');
        }

        if (null !== $chambreId) {
            $qb
                ->andWhere('c.id = :chambreId')
                ->setParameter('chambreId', $chambreId);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * @return list<Lit>
     */
    public function findAllWithChambreAndBloc(): array
    {
        return $this->createQueryBuilder('l')
            ->leftJoin('l.chambre', 'c')
            ->addSelect('c')
            ->leftJoin('c.bloc', 'b')
            ->addSelect('b')
            ->orderBy('b.libelle', 'ASC')
            ->addOrderBy('c.libelle', 'ASC')
            ->addOrderBy('l.numeroLit', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
