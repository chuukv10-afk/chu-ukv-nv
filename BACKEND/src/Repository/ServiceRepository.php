<?php

namespace App\Repository;

use App\Entity\Service;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Service>
 */
class ServiceRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Service::class);
    }

    /**
     * @return array{items: list<Service>, total: int}
     */
    public function paginate(int $page, int $limit, ?string $search = null, ?int $departementId = null): array
    {
        $qb = $this->createQueryBuilder('s')
            ->leftJoin('s.departement', 'd')
            ->addSelect('d')
            ->orderBy('s.libelle', 'ASC');

        $normalizedSearch = null !== $search ? trim($search) : '';
        if ('' !== $normalizedSearch) {
            $qb
                ->andWhere('LOWER(s.code) LIKE :search OR LOWER(s.libelle) LIKE :search OR LOWER(d.libelle) LIKE :search')
                ->setParameter('search', '%' . strtolower($normalizedSearch) . '%');
        }

        if (null !== $departementId) {
            $qb
                ->andWhere('d.id = :departementId')
                ->setParameter('departementId', $departementId);
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
     * @return list<Service>
     */
    public function findForExport(?string $search = null, ?int $departementId = null): array
    {
        $qb = $this->createQueryBuilder('s')
            ->leftJoin('s.departement', 'd')
            ->addSelect('d')
            ->orderBy('s.libelle', 'ASC');

        $normalizedSearch = null !== $search ? trim($search) : '';
        if ('' !== $normalizedSearch) {
            $qb
                ->andWhere('LOWER(s.code) LIKE :search OR LOWER(s.libelle) LIKE :search OR LOWER(d.libelle) LIKE :search')
                ->setParameter('search', '%' . strtolower($normalizedSearch) . '%');
        }

        if (null !== $departementId) {
            $qb
                ->andWhere('d.id = :departementId')
                ->setParameter('departementId', $departementId);
        }

        return $qb->getQuery()->getResult();
    }
}
