<?php

namespace App\Repository;

use App\Entity\Diagnostic;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Uid\Uuid;

/**
 * @extends ServiceEntityRepository<Diagnostic>
 */
class DiagnosticRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Diagnostic::class);
    }

    /**
     * @return array{items: list<Diagnostic>, total: int}
     */
    public function paginateByConsultation(int $consultationId, int $page, int $limit): array
    {
        $qb = $this->createQueryBuilder('d')
            ->innerJoin('d.maladie', 'm')
            ->addSelect('m')
            ->leftJoin('d.demandeExamen', 'de')
            ->leftJoin('de.examen', 'dex')
            ->addSelect('de', 'dex')
            ->andWhere('d.consultation = :consultationId')
            ->setParameter('consultationId', $consultationId)
            ->orderBy('d.createdAt', 'DESC');

        $total = (int) (clone $qb)
            ->select('COUNT(d.id)')
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
     * @return array{items: list<Diagnostic>, total: int}
     */
    public function paginateByPatientId(string $patientId, int $page, int $limit): array
    {
        $qb = $this->createQueryBuilder('d')
            ->innerJoin('d.consultation', 'c')
            ->innerJoin('c.visite', 'v')
            ->innerJoin('v.dpi', 'dpi')
            ->innerJoin('dpi.patient', 'p')
            ->innerJoin('d.maladie', 'm')
            ->leftJoin('d.demandeExamen', 'de')
            ->leftJoin('de.examen', 'dex')
            ->leftJoin('v.service', 's')
            ->addSelect('c', 'm', 'v', 's', 'de', 'dex')
            ->andWhere('p.id = :patientId')
            ->setParameter('patientId', Uuid::fromString($patientId), 'uuid')
            ->orderBy('d.createdAt', 'DESC');

        $total = (int) (clone $qb)
            ->select('COUNT(d.id)')
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
     * @return array{items: list<Diagnostic>, total: int}
     */
    public function paginateByVisiteId(int $visiteId, int $page, int $limit): array
    {
        $qb = $this->createQueryBuilder('d')
            ->innerJoin('d.consultation', 'c')
            ->innerJoin('c.visite', 'v')
            ->innerJoin('d.maladie', 'm')
            ->leftJoin('d.demandeExamen', 'de')
            ->leftJoin('de.examen', 'dex')
            ->leftJoin('v.service', 's')
            ->addSelect('c', 'm', 'v', 's', 'de', 'dex')
            ->andWhere('v.id = :visiteId')
            ->setParameter('visiteId', $visiteId)
            ->orderBy('d.createdAt', 'DESC');

        $total = (int) (clone $qb)
            ->select('COUNT(d.id)')
            ->getQuery()
            ->getSingleScalarResult();

        $items = $qb
            ->setFirstResult(max(0, ($page - 1) * $limit))
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();

        return ['items' => $items, 'total' => $total];
    }

    public function findDuplicate(int $consultationId, int $maladieId): ?Diagnostic
    {
        return $this->createQueryBuilder('d')
            ->andWhere('d.consultation = :consultationId')
            ->andWhere('d.maladie = :maladieId')
            ->setParameter('consultationId', $consultationId)
            ->setParameter('maladieId', $maladieId)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
