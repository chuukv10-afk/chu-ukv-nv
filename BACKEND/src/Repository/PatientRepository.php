<?php

namespace App\Repository;

use App\Entity\Patient;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Patient>
 */
class PatientRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Patient::class);
    }

    /**
     * @return array{items: list<Patient>, total: int}
     */
    public function paginate(
        int $page,
        int $limit,
        ?string $search = null,
        ?string $status = null,
        ?string $sexe = null,
    ): array {
        $qb = $this->createQueryBuilder('p')
            ->leftJoin('p.dpi', 'd')
            ->addSelect('d')
            ->orderBy('p.createdAt', 'DESC')
            ->distinct();

        $this->applyFilters($qb, $search, $status, $sexe);

        $countQb = clone $qb;
        $total = (int) $countQb
            ->select('COUNT(DISTINCT p.id)')
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
     * @return list<Patient>
     */
    public function findForExport(?string $search = null, ?string $status = null, ?string $sexe = null): array
    {
        $qb = $this->createQueryBuilder('p')
            ->leftJoin('p.dpi', 'd')
            ->addSelect('d')
            ->orderBy('p.nom', 'ASC')
            ->addOrderBy('p.postNom', 'ASC');

        $this->applyFilters($qb, $search, $status, $sexe);

        return $qb->getQuery()->getResult();
    }

    private function applyFilters(
        \Doctrine\ORM\QueryBuilder $qb,
        ?string $search,
        ?string $status,
        ?string $sexe,
    ): void {
        $normalizedSearch = null !== $search ? trim($search) : '';
        if ('' !== $normalizedSearch) {
            $qb
                ->andWhere(
                    'LOWER(p.nom) LIKE :search
                    OR LOWER(p.postNom) LIKE :search
                    OR LOWER(p.prenom) LIKE :search
                    OR p.telephone LIKE :search
                    OR LOWER(d.numDossier) LIKE :search',
                )
                ->setParameter('search', '%' . mb_strtolower($normalizedSearch) . '%');
        }

        if (null !== $status && '' !== trim($status)) {
            $qb
                ->andWhere('p.status = :status')
                ->setParameter('status', Patient::normalizeStatus($status));
        }

        if (null !== $sexe && '' !== trim($sexe)) {
            $qb
                ->andWhere('p.sexe = :sexe')
                ->setParameter('sexe', strtoupper(trim($sexe)));
        }
    }
}
