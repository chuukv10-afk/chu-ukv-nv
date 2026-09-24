<?php

namespace App\Repository;

use App\Entity\ActeFinancier;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ActeFinancier>
 */
class ActeFinancierRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ActeFinancier::class);
    }

    /**
     * @return array{items: list<ActeFinancier>, total: int}
     */
    public function paginate(int $page, int $limit, ?string $search = null, ?string $serviceGrille = null, ?string $statut = null): array
    {
        $qb = $this->createQueryBuilder('a')
            ->orderBy('a.serviceGrille', 'ASC')
            ->addOrderBy('a.libelle', 'ASC');

        $normalizedSearch = null !== $search ? trim($search) : '';
        if ('' !== $normalizedSearch) {
            $qb
                ->andWhere('LOWER(a.code) LIKE :search OR LOWER(a.libelle) LIKE :search OR LOWER(a.sousCategorie) LIKE :search')
                ->setParameter('search', '%' . mb_strtolower($normalizedSearch) . '%');
        }

        if (null !== $serviceGrille && '' !== trim($serviceGrille)) {
            $qb->andWhere('a.serviceGrille = :serviceGrille')->setParameter('serviceGrille', trim($serviceGrille));
        }

        if (null !== $statut && '' !== trim($statut)) {
            $qb->andWhere('a.statut = :statut')->setParameter('statut', strtoupper(trim($statut)));
        }

        $countQb = clone $qb;
        $total = (int) $countQb->select('COUNT(a.id)')->getQuery()->getSingleScalarResult();

        $items = $qb
            ->setFirstResult(max(0, ($page - 1) * $limit))
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();

        return ['items' => $items, 'total' => $total];
    }

    /**
     * @return list<string>
     */
    public function listServiceGrilles(): array
    {
        $values = $this->createQueryBuilder('a')
            ->select('DISTINCT a.serviceGrille AS serviceGrille')
            ->andWhere('a.serviceGrille IS NOT NULL')
            ->orderBy('a.serviceGrille', 'ASC')
            ->getQuery()
            ->getSingleColumnResult();

        return array_values(array_filter($values, static fn ($value): bool => is_string($value) && '' !== $value));
    }

    public function findByServiceAndLibelle(string $serviceGrille, string $libelle): ?ActeFinancier
    {
        return $this->findOneBy([
            'serviceGrille' => $serviceGrille,
            'libelle' => $libelle,
            'statut' => ActeFinancier::STATUT_ACTIF,
        ]);
    }

    /**
     * @return list<ActeFinancier>
     */
    public function findForExport(?string $search = null, ?string $serviceGrille = null): array
    {
        $qb = $this->createQueryBuilder('a')
            ->orderBy('a.serviceGrille', 'ASC')
            ->addOrderBy('a.libelle', 'ASC');

        $normalizedSearch = null !== $search ? trim($search) : '';
        if ('' !== $normalizedSearch) {
            $qb
                ->andWhere('LOWER(a.code) LIKE :search OR LOWER(a.libelle) LIKE :search OR LOWER(a.sousCategorie) LIKE :search')
                ->setParameter('search', '%' . mb_strtolower($normalizedSearch) . '%');
        }

        if (null !== $serviceGrille && '' !== trim($serviceGrille)) {
            $qb->andWhere('a.serviceGrille = :serviceGrille')->setParameter('serviceGrille', trim($serviceGrille));
        }

        return $qb->getQuery()->getResult();
    }
}
