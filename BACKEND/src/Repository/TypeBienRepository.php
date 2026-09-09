<?php

namespace App\Repository;

use App\Entity\FamilleBien;
use App\Entity\TypeBien;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<TypeBien>
 */
class TypeBienRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, TypeBien::class);
    }

    /**
     * @return array{items: list<TypeBien>, total: int}
     */
    public function paginate(int $page, int $limit, ?string $search = null, ?int $familleId = null): array
    {
        $qb = $this->createQueryBuilder('t')
            ->leftJoin('t.famille', 'f')
            ->addSelect('f')
            ->orderBy('f.ordre', 'ASC')
            ->addOrderBy('t.ordre', 'ASC')
            ->addOrderBy('t.libelle', 'ASC');

        $normalizedSearch = null !== $search ? trim($search) : '';
        if ('' !== $normalizedSearch) {
            $qb
                ->andWhere('LOWER(t.code) LIKE :search OR LOWER(t.libelle) LIKE :search')
                ->setParameter('search', '%' . mb_strtolower($normalizedSearch) . '%');
        }

        if (null !== $familleId) {
            $qb->andWhere('f.id = :familleId')->setParameter('familleId', $familleId);
        }

        $countQb = clone $qb;
        $total = (int) $countQb->select('COUNT(t.id)')->getQuery()->getSingleScalarResult();

        $items = $qb
            ->setFirstResult(max(0, ($page - 1) * $limit))
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();

        return ['items' => $items, 'total' => $total];
    }

    /**
     * @return list<TypeBien>
     */
    public function findActifs(?int $familleId = null): array
    {
        $qb = $this->createQueryBuilder('t')
            ->leftJoin('t.famille', 'f')
            ->addSelect('f')
            ->andWhere('t.statut = :statut')
            ->setParameter('statut', TypeBien::STATUT_ACTIF)
            ->orderBy('f.ordre', 'ASC')
            ->addOrderBy('t.ordre', 'ASC')
            ->addOrderBy('t.libelle', 'ASC');

        if (null !== $familleId) {
            $qb->andWhere('f.id = :familleId')->setParameter('familleId', $familleId);
        }

        return $qb->getQuery()->getResult();
    }

    public function countByFamille(FamilleBien $famille): int
    {
        return (int) $this->createQueryBuilder('t')
            ->select('COUNT(t.id)')
            ->andWhere('t.famille = :famille')
            ->setParameter('famille', $famille)
            ->getQuery()
            ->getSingleScalarResult();
    }
}
