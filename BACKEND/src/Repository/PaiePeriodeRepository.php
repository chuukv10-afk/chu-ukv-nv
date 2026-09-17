<?php

namespace App\Repository;

use App\Entity\PaiePeriode;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<PaiePeriode>
 */
class PaiePeriodeRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PaiePeriode::class);
    }

    public function findBrouillon(): ?PaiePeriode
    {
        return $this->findOneBy(['statut' => PaiePeriode::STATUT_BROUILLON]);
    }

    /**
     * @return list<PaiePeriode>
     */
    public function findAllOrdered(): array
    {
        return $this->createQueryBuilder('p')
            ->orderBy('p.annee', 'DESC')
            ->addOrderBy('p.mois', 'DESC')
            ->getQuery()
            ->getResult();
    }
}
