<?php

namespace App\Repository;

use App\Entity\Examen;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Examen>
 */
class ExamenRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Examen::class);
    }

    /**
     * @return array{items: list<Examen>, total: int}
     */
    public function paginate(int $page, int $limit, ?string $search = null, ?int $typeExamenId = null): array
    {
        $qb = $this->createQueryBuilder('e')
            ->leftJoin('e.typeExamen', 't')
            ->addSelect('t')
            ->leftJoin('e.demandeExamens', 'd')
            ->orderBy('e.libelle', 'ASC')
            ->distinct();

        $normalizedSearch = null !== $search ? trim($search) : '';
        if ('' !== $normalizedSearch) {
            $qb
                ->andWhere('LOWER(e.code) LIKE :search OR LOWER(e.libelle) LIKE :search OR LOWER(t.libelle) LIKE :search')
                ->setParameter('search', '%' . strtolower($normalizedSearch) . '%');
        }

        if (null !== $typeExamenId) {
            $qb
                ->andWhere('t.id = :typeExamenId')
                ->setParameter('typeExamenId', $typeExamenId);
        }

        $countQb = clone $qb;
        $total = (int) $countQb
            ->select('COUNT(DISTINCT e.id)')
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
     * @return list<Examen>
     */
    public function findForExport(?string $search = null, ?int $typeExamenId = null): array
    {
        $qb = $this->createQueryBuilder('e')
            ->leftJoin('e.typeExamen', 't')
            ->addSelect('t')
            ->leftJoin('e.demandeExamens', 'd')
            ->orderBy('e.libelle', 'ASC')
            ->distinct();

        $normalizedSearch = null !== $search ? trim($search) : '';
        if ('' !== $normalizedSearch) {
            $qb
                ->andWhere('LOWER(e.code) LIKE :search OR LOWER(e.libelle) LIKE :search OR LOWER(t.libelle) LIKE :search')
                ->setParameter('search', '%' . strtolower($normalizedSearch) . '%');
        }

        if (null !== $typeExamenId) {
            $qb
                ->andWhere('t.id = :typeExamenId')
                ->setParameter('typeExamenId', $typeExamenId);
        }

        return $qb->getQuery()->getResult();
    }
}
