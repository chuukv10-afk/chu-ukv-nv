<?php

namespace App\Repository;

use App\Entity\DemandeExamen;
use App\Security\PersonnelAccessScope;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Uid\Uuid;

/**
 * @extends ServiceEntityRepository<DemandeExamen>
 */
class DemandeExamenRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, DemandeExamen::class);
    }

    /**
     * @return array{items: list<DemandeExamen>, total: int}
     */
    public function paginateByConsultation(int $consultationId, int $page, int $limit): array
    {
        $qb = $this->createQueryBuilder('d')
            ->innerJoin('d.examen', 'e')->addSelect('e')
            ->leftJoin('e.typeExamen', 'te')->addSelect('te')
            ->leftJoin('d.prescripteur', 'pr')->addSelect('pr')
            ->leftJoin('d.diagnostics', 'diag')->addSelect('diag')
            ->leftJoin('diag.maladie', 'dm')->addSelect('dm')
            ->andWhere('d.consultation = :consultationId')
            ->setParameter('consultationId', $consultationId)
            ->orderBy('d.demandeAt', 'DESC')
            ->addOrderBy('d.id', 'DESC');

        return $this->paginateQuery($qb, $page, $limit);
    }

    /**
     * @return array{items: list<DemandeExamen>, total: int}
     */
    public function paginateByPatientId(string $patientId, int $page, int $limit): array
    {
        $qb = $this->baseQueryBuilder()
            ->andWhere('p.id = :patientId')
            ->setParameter('patientId', Uuid::fromString($patientId), 'uuid');

        return $this->paginateQuery($qb, $page, $limit);
    }

    /**
     * @return array{items: list<DemandeExamen>, total: int}
     */
    public function paginateGlobal(
        int $page,
        int $limit,
        ?string $search = null,
        ?string $statut = null,
        ?int $typeExamenId = null,
        ?PersonnelAccessScope $accessScope = null,
    ): array {
        $qb = $this->baseQueryBuilder();

        if (null !== $statut && '' !== trim($statut)) {
            $qb
                ->andWhere('d.statut = :statut')
                ->setParameter('statut', DemandeExamen::normalizeStatut($statut));
        }

        if (null !== $typeExamenId) {
            $qb
                ->andWhere('te.id = :typeExamenId')
                ->setParameter('typeExamenId', $typeExamenId);
        }

        if (null !== $search && '' !== trim($search)) {
            $term = '%' . mb_strtolower(trim($search)) . '%';
            $qb
                ->andWhere(
                    'LOWER(p.nom) LIKE :search OR LOWER(p.postNom) LIKE :search OR LOWER(p.prenom) LIKE :search
                    OR LOWER(dpi.numDossier) LIKE :search OR LOWER(e.code) LIKE :search OR LOWER(e.libelle) LIKE :search',
                )
                ->setParameter('search', $term);
        }

        $this->applyAccessScope($qb, $accessScope);

        return $this->paginateQuery($qb, $page, $limit);
    }

    private function baseQueryBuilder(): QueryBuilder
    {
        return $this->createQueryBuilder('d')
            ->innerJoin('d.examen', 'e')->addSelect('e')
            ->leftJoin('e.typeExamen', 'te')->addSelect('te')
            ->leftJoin('d.prescripteur', 'pr')->addSelect('pr')
            ->leftJoin('d.diagnostics', 'diag')->addSelect('diag')
            ->leftJoin('diag.maladie', 'dm')->addSelect('dm')
            ->innerJoin('d.consultation', 'c')->addSelect('c')
            ->innerJoin('c.visite', 'v')->addSelect('v')
            ->innerJoin('v.dpi', 'dpi')->addSelect('dpi')
            ->innerJoin('dpi.patient', 'p')->addSelect('p')
            ->leftJoin('v.service', 's')->addSelect('s')
            ->orderBy('d.demandeAt', 'DESC')
            ->addOrderBy('d.id', 'DESC');
    }

    /**
     * @return array{items: list<DemandeExamen>, total: int}
     */
    private function paginateQuery(QueryBuilder $qb, int $page, int $limit): array
    {
        $total = (int) (clone $qb)
            ->select('COUNT(DISTINCT d.id)')
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

    private function applyAccessScope(QueryBuilder $qb, ?PersonnelAccessScope $accessScope): void
    {
        if (null === $accessScope || $accessScope->unrestricted) {
            return;
        }

        $serviceIds = $accessScope->serviceIds;
        if ([] === $serviceIds) {
            $qb->andWhere('1 = 0');

            return;
        }

        $qb
            ->andWhere('s.id IN (:scopeServiceIds)')
            ->setParameter('scopeServiceIds', $serviceIds);
    }
}
