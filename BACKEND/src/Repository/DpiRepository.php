<?php

namespace App\Repository;

use App\Entity\Dpi;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Dpi>
 */
class DpiRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Dpi::class);
    }

    public function getNextSequenceForYear(int $year): int
    {
        $prefix = sprintf('DPI-%d-', $year);
        $maxNumDossier = $this->createQueryBuilder('d')
            ->select('MAX(d.numDossier)')
            ->where('d.numDossier LIKE :prefix')
            ->setParameter('prefix', $prefix . '%')
            ->getQuery()
            ->getSingleScalarResult();

        if (!is_string($maxNumDossier) || !str_starts_with($maxNumDossier, $prefix)) {
            return 1;
        }

        $sequencePart = substr($maxNumDossier, strlen($prefix));
        if (!ctype_digit($sequencePart)) {
            return 1;
        }

        return ((int) $sequencePart) + 1;
    }
}
