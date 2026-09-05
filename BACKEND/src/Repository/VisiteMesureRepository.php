<?php

namespace App\Repository;

use App\Entity\VisiteMesure;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<VisiteMesure>
 */
class VisiteMesureRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, VisiteMesure::class);
    }

    /**
     * @return list<VisiteMesure>
     */
    public function findByVisite(int $visiteId): array
    {
        return $this->createQueryBuilder('vm')
            ->addSelect('sv')
            ->leftJoin('vm.signeVital', 'sv')
            ->andWhere('vm.visite = :visiteId')
            ->setParameter('visiteId', $visiteId)
            ->orderBy('vm.measuredAt', 'DESC')
            ->addOrderBy('vm.id', 'DESC')
            ->getQuery()
            ->getResult();
    }
}
