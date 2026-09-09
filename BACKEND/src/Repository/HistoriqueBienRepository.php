<?php

namespace App\Repository;

use App\Entity\BienPatrimonial;
use App\Entity\HistoriqueBien;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<HistoriqueBien>
 */
class HistoriqueBienRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, HistoriqueBien::class);
    }

    /**
     * @return list<HistoriqueBien>
     */
    public function findByBien(BienPatrimonial $bien): array
    {
        return $this->createQueryBuilder('h')
            ->leftJoin('h.createdBy', 'p')->addSelect('p')
            ->leftJoin('h.serviceAvant', 'sa')->addSelect('sa')
            ->leftJoin('h.serviceApres', 'sp')->addSelect('sp')
            ->leftJoin('h.localAvant', 'la')->addSelect('la')
            ->leftJoin('h.localApres', 'lp')->addSelect('lp')
            ->andWhere('h.bien = :bien')
            ->setParameter('bien', $bien)
            ->orderBy('h.createdAt', 'DESC')
            ->addOrderBy('h.id', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function hasType(BienPatrimonial $bien, string $type): bool
    {
        $count = (int) $this->createQueryBuilder('h')
            ->select('COUNT(h.id)')
            ->andWhere('h.bien = :bien')
            ->andWhere('h.type = :type')
            ->setParameter('bien', $bien)
            ->setParameter('type', $type)
            ->getQuery()
            ->getSingleScalarResult();

        return $count > 0;
    }
}
