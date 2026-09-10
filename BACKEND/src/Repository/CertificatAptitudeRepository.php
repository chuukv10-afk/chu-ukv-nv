<?php

namespace App\Repository;

use App\Entity\CertificatAptitude;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<CertificatAptitude>
 */
class CertificatAptitudeRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, CertificatAptitude::class);
    }

    /**
     * @return array{items: list<CertificatAptitude>, total: int}
     */
    public function paginate(
        int $page,
        int $limit,
        ?string $search = null,
        ?int $annee = null,
        ?string $statut = null,
        ?string $verdict = null,
        ?string $motif = null,
        ?int $serviceId = null,
    ): array {
        $qb = $this->createFilteredQueryBuilder($search, $annee, $statut, $verdict, $motif, $serviceId);

        $total = (int) (clone $qb)
            ->select('COUNT(c.id)')
            ->resetDQLPart('orderBy')
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
     * @return list<CertificatAptitude>
     */
    public function findForExport(
        ?string $search = null,
        ?int $annee = null,
        ?string $statut = null,
        ?string $verdict = null,
        ?string $motif = null,
        ?int $serviceId = null,
    ): array {
        return $this->createFilteredQueryBuilder($search, $annee, $statut, $verdict, $motif, $serviceId)
            ->getQuery()
            ->getResult();
    }

    public function nextSequenceForYear(int $year): int
    {
        $max = $this->getEntityManager()->getConnection()->fetchOne(
            'SELECT MAX(CAST(SUBSTRING_INDEX(numero, \' \', 1) AS UNSIGNED))
             FROM certificat_aptitude
             WHERE numero LIKE :pattern',
            ['pattern' => '% / CHU-UKV / CAP / ' . $year],
        );

        return ((int) $max) + 1;
    }

    private function createFilteredQueryBuilder(
        ?string $search,
        ?int $annee,
        ?string $statut,
        ?string $verdict,
        ?string $motif,
        ?int $serviceId,
    ): \Doctrine\ORM\QueryBuilder {
        $qb = $this->createQueryBuilder('c')
            ->leftJoin('c.service', 's')->addSelect('s')
            ->leftJoin('c.patient', 'p')->addSelect('p')
            ->leftJoin('c.signePar', 'sp')->addSelect('sp')
            ->orderBy('c.createdAt', 'DESC')
            ->addOrderBy('c.id', 'DESC');

        $normalizedSearch = null !== $search ? trim($search) : '';
        if ('' !== $normalizedSearch) {
            $term = '%' . mb_strtolower($normalizedSearch) . '%';
            $qb
                ->andWhere(
                    'LOWER(c.nom) LIKE :search OR LOWER(c.postNom) LIKE :search OR LOWER(c.prenom) LIKE :search
                    OR LOWER(c.numero) LIKE :search OR LOWER(s.libelle) LIKE :search',
                )
                ->setParameter('search', $term);
        }

        if (null !== $annee && $annee > 0) {
            $qb->andWhere('c.annee = :annee')->setParameter('annee', $annee);
        }

        if (null !== $statut && '' !== trim($statut)) {
            $qb->andWhere('c.statut = :statut')->setParameter('statut', strtoupper(trim($statut)));
        }

        if (null !== $verdict && '' !== trim($verdict)) {
            $qb->andWhere('c.verdict = :verdict')->setParameter('verdict', strtoupper(trim($verdict)));
        }

        if (null !== $motif && '' !== trim($motif)) {
            $qb->andWhere('c.motif = :motif')->setParameter('motif', strtoupper(trim($motif)));
        }

        if (null !== $serviceId) {
            $qb->andWhere('s.id = :serviceId')->setParameter('serviceId', $serviceId);
        }

        return $qb;
    }
}
