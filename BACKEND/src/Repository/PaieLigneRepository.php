<?php

namespace App\Repository;

use App\Entity\PaieLigne;
use App\Entity\PaiePeriode;
use App\Entity\Personnel;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<PaieLigne>
 */
class PaieLigneRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PaieLigne::class);
    }

    public function findOneByPeriodeAndPersonnel(PaiePeriode $periode, Personnel $personnel): ?PaieLigne
    {
        return $this->findOneBy([
            'periode' => $periode,
            'personnel' => $personnel,
        ]);
    }

    /**
     * @return list<PaieLigne>
     */
    public function findByPeriodeOrdered(PaiePeriode $periode, ?bool $inclus = null): array
    {
        $qb = $this->createQueryBuilder('l')
            ->andWhere('l.periode = :periode')
            ->setParameter('periode', $periode)
            ->orderBy('l.nomComplet', 'ASC');

        if (null !== $inclus) {
            $qb->andWhere('l.inclus = :inclus')->setParameter('inclus', $inclus);
        }

        return $qb->getQuery()->getResult();
    }
}
