<?php

namespace App\Repository;

use App\Entity\OrganisationPartenaire;
use App\Repository\Trait\CodeLibellePaginateTrait;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<OrganisationPartenaire>
 */
class OrganisationPartenaireRepository extends ServiceEntityRepository
{
    use CodeLibellePaginateTrait;

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, OrganisationPartenaire::class);
    }

    /**
     * @return array{items: list<OrganisationPartenaire>, total: int}
     */
    public function paginate(int $page, int $limit, ?string $search = null, ?string $typeInstitution = null): array
    {
        $qb = $this->createQueryBuilder('o')
            ->orderBy('o.libelle', 'ASC');

        $normalizedSearch = null !== $search ? trim($search) : '';
        if ('' !== $normalizedSearch) {
            $qb
                ->andWhere('LOWER(o.code) LIKE :search OR LOWER(o.libelle) LIKE :search')
                ->setParameter('search', '%' . strtolower($normalizedSearch) . '%');
        }
        if (null !== $typeInstitution && '' !== trim($typeInstitution)) {
            $qb
                ->andWhere('o.typeInstitution = :type')
                ->setParameter('type', strtoupper(trim($typeInstitution)));
        }

        $total = (int) (clone $qb)
            ->select('COUNT(o.id)')
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
     * @return list<OrganisationPartenaire>
     */
    public function findActifsOrdered(): array
    {
        return $this->findBy(
            ['statut' => OrganisationPartenaire::STATUT_ACTIF],
            ['libelle' => 'ASC'],
        );
    }
}
