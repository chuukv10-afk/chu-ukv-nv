<?php

namespace App\Repository;

use App\Entity\LocalIntendance;
use App\Entity\Service;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<LocalIntendance>
 */
class LocalIntendanceRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, LocalIntendance::class);
    }

    /**
     * @return array{items: list<LocalIntendance>, total: int}
     */
    public function paginate(int $page, int $limit, ?string $search = null, ?int $serviceId = null): array
    {
        $qb = $this->createQueryBuilder('l')
            ->leftJoin('l.service', 's')
            ->addSelect('s')
            ->orderBy('s.libelle', 'ASC')
            ->addOrderBy('l.ordre', 'ASC')
            ->addOrderBy('l.libelle', 'ASC');

        $normalizedSearch = null !== $search ? trim($search) : '';
        if ('' !== $normalizedSearch) {
            $qb
                ->andWhere('LOWER(l.code) LIKE :search OR LOWER(l.libelle) LIKE :search OR LOWER(s.libelle) LIKE :search')
                ->setParameter('search', '%' . mb_strtolower($normalizedSearch) . '%');
        }

        if (null !== $serviceId) {
            $qb->andWhere('s.id = :serviceId')->setParameter('serviceId', $serviceId);
        }

        $countQb = clone $qb;
        $total = (int) $countQb->select('COUNT(l.id)')->getQuery()->getSingleScalarResult();

        $items = $qb
            ->setFirstResult(max(0, ($page - 1) * $limit))
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();

        return ['items' => $items, 'total' => $total];
    }

    /**
     * @return list<LocalIntendance>
     */
    public function findActifsByService(int $serviceId): array
    {
        return $this->createQueryBuilder('l')
            ->leftJoin('l.service', 's')
            ->addSelect('s')
            ->andWhere('s.id = :serviceId')
            ->andWhere('l.statut = :statut')
            ->setParameter('serviceId', $serviceId)
            ->setParameter('statut', LocalIntendance::STATUT_ACTIF)
            ->orderBy('l.ordre', 'ASC')
            ->addOrderBy('l.libelle', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function findOneByServiceAndCode(Service $service, string $code): ?LocalIntendance
    {
        return $this->findOneBy(['service' => $service, 'code' => $code]);
    }
}
