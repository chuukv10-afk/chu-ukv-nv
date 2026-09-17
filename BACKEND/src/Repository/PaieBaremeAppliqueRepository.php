<?php

namespace App\Repository;

use App\Entity\PaieBaremeApplique;
use App\Entity\PaiePeriode;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<PaieBaremeApplique>
 */
class PaieBaremeAppliqueRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PaieBaremeApplique::class);
    }

    /**
     * @return list<PaieBaremeApplique>
     */
    public function findByPeriodeOrdered(PaiePeriode $periode): array
    {
        return $this->createQueryBuilder('b')
            ->andWhere('b.periode = :periode')
            ->setParameter('periode', $periode)
            ->orderBy('b.fonctionLibelle', 'ASC')
            ->addOrderBy('b.gradeLibelle', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
