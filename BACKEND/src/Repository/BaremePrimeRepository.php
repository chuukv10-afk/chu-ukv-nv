<?php

namespace App\Repository;

use App\Entity\BaremePrime;
use App\Entity\Fonction;
use App\Entity\Grade;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<BaremePrime>
 */
class BaremePrimeRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, BaremePrime::class);
    }

    public function findOneByGradeAndFonction(?Grade $grade, Fonction $fonction): ?BaremePrime
    {
        return $this->findOneBy([
            'grade' => $grade,
            'fonction' => $fonction,
        ]);
    }

    /**
     * @return list<BaremePrime>
     */
    public function findAllOrdered(): array
    {
        return $this->createQueryBuilder('b')
            ->leftJoin('b.grade', 'g')->addSelect('g')
            ->innerJoin('b.fonction', 'f')->addSelect('f')
            ->orderBy('f.libelle', 'ASC')
            ->addOrderBy('g.libelle', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
