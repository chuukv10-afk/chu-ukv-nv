<?php

namespace App\Repository;

use App\Entity\Personnel;
use App\Security\PersonnelAccessScope;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Uid\Uuid;

/**
 * @extends ServiceEntityRepository<Personnel>
 */
class PersonnelRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Personnel::class);
    }

    public function paginate(
        int $page,
        int $limit,
        ?string $search = null,
        ?string $status = null,
        ?string $type = null,
        ?int $serviceId = null,
        ?string $sexe = null,
        ?PersonnelAccessScope $accessScope = null,
    ): array {
        $qb = $this->createFilteredQueryBuilder(
            $search,
            $status,
            $type,
            $serviceId,
            $sexe,
            $accessScope,
        );

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

        return [
            'items' => $items,
            'total' => $total,
        ];
    }

    /**
     * @return list<Personnel>
     */
    public function findForExport(
        ?string $search = null,
        ?string $status = null,
        ?string $type = null,
        ?int $serviceId = null,
        ?string $sexe = null,
        ?PersonnelAccessScope $accessScope = null,
    ): array {
        return $this->createFilteredQueryBuilder(
            $search,
            $status,
            $type,
            $serviceId,
            $sexe,
            $accessScope,
        )
            ->getQuery()
            ->getResult();
    }

    public function findOneByTelephoneForAnotherPersonnel(string $telephone, Uuid $excludeId): ?Personnel
    {
        return $this->createQueryBuilder('p')
            ->andWhere('p.telephone = :telephone')
            ->andWhere('p.id != :excludeId')
            ->andWhere('p.status != :deletedStatus')
            ->setParameter('telephone', trim($telephone))
            ->setParameter('excludeId', $excludeId, 'uuid')
            ->setParameter('deletedStatus', Personnel::STATUS_SUPPRIME)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function findOneByMatriculeForAnotherPersonnel(string $matricule, Uuid $excludeId): ?Personnel
    {
        return $this->createQueryBuilder('p')
            ->andWhere('p.matricule = :matricule')
            ->andWhere('p.id != :excludeId')
            ->andWhere('p.status != :deletedStatus')
            ->setParameter('matricule', trim($matricule))
            ->setParameter('excludeId', $excludeId, 'uuid')
            ->setParameter('deletedStatus', Personnel::STATUS_SUPPRIME)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function existsByTelephone(string $telephone): bool
    {
        return (int) $this->createQueryBuilder('p')
            ->select('COUNT(p.id)')
            ->andWhere('p.telephone = :telephone')
            ->andWhere('p.status != :deletedStatus')
            ->setParameter('telephone', trim($telephone))
            ->setParameter('deletedStatus', Personnel::STATUS_SUPPRIME)
            ->getQuery()
            ->getSingleScalarResult() > 0;
    }

    public function existsByMatricule(string $matricule): bool
    {
        return (int) $this->createQueryBuilder('p')
            ->select('COUNT(p.id)')
            ->andWhere('p.matricule = :matricule')
            ->andWhere('p.status != :deletedStatus')
            ->setParameter('matricule', trim($matricule))
            ->setParameter('deletedStatus', Personnel::STATUS_SUPPRIME)
            ->getQuery()
            ->getSingleScalarResult() > 0;
    }

    private function createFilteredQueryBuilder(
        ?string $search,
        ?string $status,
        ?string $type,
        ?int $serviceId,
        ?string $sexe,
        ?PersonnelAccessScope $accessScope,
    ): QueryBuilder {
        $qb = $this->createQueryBuilder('p')
            ->leftJoin('p.grade', 'g')->addSelect('g')
            ->leftJoin('p.service', 's')->addSelect('s')
            ->leftJoin('p.roleAssignments', 'ra')->addSelect('ra')
            ->leftJoin('ra.role', 'r')->addSelect('r')
            ->orderBy('p.nom', 'ASC')
            ->addOrderBy('p.postNom', 'ASC')
            ->distinct();

        $qb->andWhere('p.status != :deletedStatus')
            ->setParameter('deletedStatus', Personnel::STATUS_SUPPRIME);

        $this->applyAccessScope($qb, $accessScope);

        $normalizedSearch = null !== $search ? trim($search) : '';
        if ('' !== $normalizedSearch) {
            $qb
                ->andWhere(
                    'LOWER(p.nom) LIKE :search OR LOWER(p.postNom) LIKE :search OR LOWER(p.prenom) LIKE :search'
                    . ' OR LOWER(p.matricule) LIKE :search OR p.telephone LIKE :search'
                )
                ->setParameter('search', '%' . strtolower($normalizedSearch) . '%');
        }

        if (null !== $status && '' !== trim($status)) {
            $qb
                ->andWhere('p.status = :status')
                ->setParameter('status', Personnel::normalizeStatus($status));
        }

        if (null !== $type && '' !== trim($type)) {
            $qb
                ->andWhere('p.type = :type')
                ->setParameter('type', Personnel::normalizeType($type));
        }

        if (null !== $serviceId) {
            $qb
                ->andWhere('p.service = :serviceId')
                ->setParameter('serviceId', $serviceId);
        }

        if (null !== $sexe && '' !== trim($sexe)) {
            $qb
                ->andWhere('p.sexe = :sexe')
                ->setParameter('sexe', strtoupper(trim($sexe)));
        }

        return $qb;
    }

    private function applyAccessScope(QueryBuilder $qb, ?PersonnelAccessScope $accessScope): void
    {
        if (null === $accessScope || !$accessScope->isRestricted()) {
            return;
        }

        if ($accessScope->allowsNothing()) {
            $qb->andWhere('1 = 0');

            return;
        }

        $conditions = [];

        if ([] !== $accessScope->serviceIds) {
            $conditions[] = 's.id IN (:accessScopeServiceIds)';
            $qb->setParameter('accessScopeServiceIds', $accessScope->serviceIds);
        }

        if ([] !== $accessScope->departementIds) {
            $qb->leftJoin('s.departement', 'accessScopeDept');
            $conditions[] = 'accessScopeDept.id IN (:accessScopeDepartementIds)';
            $qb->setParameter('accessScopeDepartementIds', $accessScope->departementIds);
        }

        if ([] === $conditions) {
            $qb->andWhere('1 = 0');

            return;
        }

        $qb->andWhere('(' . implode(' OR ', $conditions) . ')');
    }
}
