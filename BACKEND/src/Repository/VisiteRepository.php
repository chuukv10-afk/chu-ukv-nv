<?php

namespace App\Repository;

use App\Entity\Consultation;
use App\Entity\Triage;
use App\Entity\Visite;
use App\Security\PersonnelAccessScope;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Uid\Uuid;

/**
 * @extends ServiceEntityRepository<Visite>
 */
class VisiteRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Visite::class);
    }

    /**
     * @return array{items: list<Visite>, total: int}
     */
    public function paginate(
        int $page,
        int $limit,
        ?string $search = null,
        ?string $statut = null,
        ?int $serviceId = null,
        ?int $dpiId = null,
        ?string $patientId = null,
        ?PersonnelAccessScope $accessScope = null,
        ?\DateTimeImmutable $enterFrom = null,
        ?\DateTimeImmutable $enterToExclusive = null,
        bool $pendingHospitalization = false,
    ): array {
        $qb = $this->createFilteredQueryBuilder(
            $search,
            $statut,
            $serviceId,
            $dpiId,
            $patientId,
            $accessScope,
            $enterFrom,
            $enterToExclusive,
            $pendingHospitalization,
        );

        $countQb = clone $qb;
        $total = (int) $countQb
            ->select('COUNT(DISTINCT v.id)')
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
     * @return list<Visite>
     */
    public function findForExport(
        ?string $search = null,
        ?string $statut = null,
        ?int $serviceId = null,
        ?int $dpiId = null,
        ?string $patientId = null,
        ?PersonnelAccessScope $accessScope = null,
        ?\DateTimeImmutable $enterFrom = null,
        ?\DateTimeImmutable $enterToExclusive = null,
        bool $pendingHospitalization = false,
    ): array {
        $qb = $this->createFilteredQueryBuilder(
            $search,
            $statut,
            $serviceId,
            $dpiId,
            $patientId,
            $accessScope,
            $enterFrom,
            $enterToExclusive,
            $pendingHospitalization,
        );

        return $qb->getQuery()->getResult();
    }

    public function countActiveByDpi(int $dpiId, ?int $excludeVisiteId = null): int
    {
        $qb = $this->createQueryBuilder('v')
            ->select('COUNT(v.id)')
            ->andWhere('v.dpi = :dpiId')
            ->andWhere('v.statut IN (:activeStatuts)')
            ->setParameter('dpiId', $dpiId)
            ->setParameter('activeStatuts', Visite::getActiveStatuts());

        if (null !== $excludeVisiteId) {
            $qb
                ->andWhere('v.id != :excludeVisiteId')
                ->setParameter('excludeVisiteId', $excludeVisiteId);
        }

        return (int) $qb->getQuery()->getSingleScalarResult();
    }

    /**
     * @return list<Visite>
     */
    public function findWithConsultationTriageMissingConsultation(): array
    {
        return $this->createQueryBuilder('v')
            ->innerJoin('v.triage', 't')->addSelect('t')
            ->leftJoin('v.consultations', 'c')
            ->andWhere('t.typeEntree = :typeEntree')
            ->andWhere('c.id IS NULL')
            ->setParameter('typeEntree', Triage::TYPE_CONSULTATION)
            ->orderBy('v.id', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function findActiveHospitalisationByLit(int $litId, ?int $excludeVisiteId = null): ?Visite
    {
        $qb = $this->createQueryBuilder('v')
            ->andWhere('v.lit = :litId')
            ->andWhere('v.statut = :statut')
            ->setParameter('litId', $litId)
            ->setParameter('statut', Visite::STATUT_HOSPITALISE)
            ->setMaxResults(1);

        if (null !== $excludeVisiteId) {
            $qb
                ->andWhere('v.id != :excludeVisiteId')
                ->setParameter('excludeVisiteId', $excludeVisiteId);
        }

        return $qb->getQuery()->getOneOrNullResult();
    }

    /**
     * @param list<int> $visiteIds
     *
     * @return list<int>
     */
    public function findPendingHospitalizationIds(array $visiteIds): array
    {
        if ([] === $visiteIds) {
            return [];
        }

        $rows = $this->createQueryBuilder('v')
            ->select('DISTINCT v.id')
            ->innerJoin('v.consultations', 'cons')
            ->andWhere('v.id IN (:ids)')
            ->andWhere('v.statut = :enCours')
            ->andWhere('cons.needsHospitalization = true')
            ->setParameter('ids', $visiteIds)
            ->setParameter('enCours', Visite::STATUT_EN_COURS)
            ->getQuery()
            ->getSingleColumnResult();

        return array_map('intval', $rows);
    }

    private function createFilteredQueryBuilder(
        ?string $search,
        ?string $statut,
        ?int $serviceId,
        ?int $dpiId,
        ?string $patientId,
        ?PersonnelAccessScope $accessScope,
        ?\DateTimeImmutable $enterFrom = null,
        ?\DateTimeImmutable $enterToExclusive = null,
        bool $pendingHospitalization = false,
    ): QueryBuilder {
        $qb = $this->createQueryBuilder('v')
            ->leftJoin('v.dpi', 'd')->addSelect('d')
            ->leftJoin('d.patient', 'p')->addSelect('p')
            ->leftJoin('v.service', 's')->addSelect('s')
            ->leftJoin('s.departement', 'dep')->addSelect('dep')
            ->leftJoin('v.lit', 'l')->addSelect('l')
            ->leftJoin('l.chambre', 'c')->addSelect('c')
            ->leftJoin('c.bloc', 'b')->addSelect('b')
            ->orderBy('v.enterAt', 'DESC')
            ->distinct();

        $this->applyAccessScope($qb, $accessScope);

        $normalizedSearch = null !== $search ? trim($search) : '';
        if ('' !== $normalizedSearch) {
            $qb
                ->andWhere(
                    'LOWER(p.nom) LIKE :search OR LOWER(p.postNom) LIKE :search OR LOWER(p.prenom) LIKE :search
                    OR LOWER(d.numDossier) LIKE :search OR LOWER(s.libelle) LIKE :search',
                )
                ->setParameter('search', '%' . mb_strtolower($normalizedSearch) . '%');
        }

        if (null !== $statut && '' !== trim($statut)) {
            $qb
                ->andWhere('v.statut = :statut')
                ->setParameter('statut', Visite::normalizeStatut($statut));
        }

        if (null !== $serviceId) {
            $qb
                ->andWhere('s.id = :serviceId')
                ->setParameter('serviceId', $serviceId);
        }

        if (null !== $dpiId) {
            $qb
                ->andWhere('d.id = :dpiId')
                ->setParameter('dpiId', $dpiId);
        }

        if (null !== $patientId && '' !== trim($patientId)) {
            $qb
                ->andWhere('p.id = :patientId')
                ->setParameter('patientId', Uuid::fromString($patientId), 'uuid');
        }

        if (null !== $enterFrom) {
            $qb
                ->andWhere('v.enterAt >= :enterFrom')
                ->setParameter('enterFrom', $enterFrom);
        }

        if (null !== $enterToExclusive) {
            $qb
                ->andWhere('v.enterAt < :enterToExclusive')
                ->setParameter('enterToExclusive', $enterToExclusive);
        }

        if ($pendingHospitalization) {
            $qb
                ->andWhere('v.statut = :pendingHospStatut')
                ->andWhere(
                    'EXISTS (
                        SELECT 1 FROM '.Consultation::class.' consHosp
                        WHERE consHosp.visite = v AND consHosp.needsHospitalization = true
                    )',
                )
                ->setParameter('pendingHospStatut', Visite::STATUT_EN_COURS);
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
            $conditions[] = 'dep.id IN (:accessScopeDepartementIds)';
            $qb->setParameter('accessScopeDepartementIds', $accessScope->departementIds);
        }

        if ([] === $conditions) {
            $qb->andWhere('1 = 0');

            return;
        }

        $qb->andWhere('(' . implode(' OR ', $conditions) . ')');
    }
}
