<?php

namespace App\Repository;

use App\Entity\Consultation;
use App\Security\PersonnelAccessScope;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Uid\Uuid;

/**
 * @extends ServiceEntityRepository<Consultation>
 */
class ConsultationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Consultation::class);
    }

    /**
     * @return array{items: list<Consultation>, total: int}
     */
    public function paginate(
        int $page,
        int $limit,
        ?string $search = null,
        ?string $statut = null,
        ?int $visiteId = null,
        ?string $patientId = null,
        ?PersonnelAccessScope $accessScope = null,
    ): array {
        $qb = $this->createFilteredQueryBuilder(
            $search,
            $statut,
            $visiteId,
            $patientId,
            $accessScope,
        );

        $countQb = clone $qb;
        $total = (int) $countQb
            ->select('COUNT(DISTINCT c.id)')
            ->getQuery()
            ->getSingleScalarResult();

        $items = $qb
            ->setFirstResult(max(0, ($page - 1) * $limit))
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();

        return ['items' => $items, 'total' => $total];
    }

    public function countActiveByVisite(int $visiteId, ?int $excludeConsultationId = null): int
    {
        $qb = $this->createQueryBuilder('c')
            ->select('COUNT(c.id)')
            ->andWhere('c.visite = :visiteId')
            ->andWhere('c.statut IN (:activeStatuts)')
            ->setParameter('visiteId', $visiteId)
            ->setParameter('activeStatuts', [Consultation::STATUT_PLANIFIEE, Consultation::STATUT_EN_COURS]);

        if (null !== $excludeConsultationId) {
            $qb
                ->andWhere('c.id != :excludeConsultationId')
                ->setParameter('excludeConsultationId', $excludeConsultationId);
        }

        return (int) $qb->getQuery()->getSingleScalarResult();
    }

    public function findActiveByVisite(int $visiteId): ?Consultation
    {
        return $this->createQueryBuilder('c')
            ->andWhere('c.visite = :visiteId')
            ->andWhere('c.statut IN (:activeStatuts)')
            ->setParameter('visiteId', $visiteId)
            ->setParameter('activeStatuts', [Consultation::STATUT_PLANIFIEE, Consultation::STATUT_EN_COURS])
            ->orderBy('c.consultedAt', 'DESC')
            ->addOrderBy('c.id', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function findPreviousWardRound(int $visiteId, int $currentId, \DateTimeImmutable $consultedAt): ?Consultation
    {
        return $this->createQueryBuilder('c')
            ->andWhere('c.visite = :visiteId')
            ->andWhere('c.typeConsultation = :type')
            ->andWhere('c.statut != :annulee')
            ->andWhere('c.consultedAt < :consultedAt OR (c.consultedAt = :consultedAt AND c.id < :currentId)')
            ->setParameter('visiteId', $visiteId)
            ->setParameter('type', Consultation::TYPE_AU_LIT)
            ->setParameter('annulee', Consultation::STATUT_ANNULEE)
            ->setParameter('consultedAt', $consultedAt)
            ->setParameter('currentId', $currentId)
            ->orderBy('c.consultedAt', 'DESC')
            ->addOrderBy('c.id', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    private function createFilteredQueryBuilder(
        ?string $search,
        ?string $statut,
        ?int $visiteId,
        ?string $patientId,
        ?PersonnelAccessScope $accessScope,
    ): QueryBuilder {
        $qb = $this->createQueryBuilder('c')
            ->leftJoin('c.visite', 'v')->addSelect('v')
            ->leftJoin('v.dpi', 'd')->addSelect('d')
            ->leftJoin('d.patient', 'p')->addSelect('p')
            ->leftJoin('v.service', 's')->addSelect('s')
            ->leftJoin('c.openedBy', 'ob')->addSelect('ob')
            ->orderBy('c.consultedAt', 'DESC')
            ->addOrderBy('c.id', 'DESC');

        if (null !== $statut && '' !== trim($statut)) {
            $qb
                ->andWhere('c.statut = :statut')
                ->setParameter('statut', Consultation::normalizeStatut($statut));
        }

        if (null !== $visiteId) {
            $qb
                ->andWhere('c.visite = :visiteId')
                ->setParameter('visiteId', $visiteId);
        }

        if (null !== $patientId && '' !== trim($patientId)) {
            $qb
                ->andWhere('p.id = :patientId')
                ->setParameter('patientId', Uuid::fromString(trim($patientId)), 'uuid');
        }

        if (null !== $search && '' !== trim($search)) {
            $term = '%' . mb_strtolower(trim($search)) . '%';
            $qb
                ->andWhere(
                    'LOWER(p.nom) LIKE :search OR LOWER(p.postNom) LIKE :search OR LOWER(p.prenom) LIKE :search
                    OR LOWER(d.numDossier) LIKE :search OR LOWER(c.motif) LIKE :search
                    OR LOWER(ob.nom) LIKE :search OR LOWER(ob.postNom) LIKE :search OR LOWER(ob.prenom) LIKE :search',
                )
                ->setParameter('search', $term);
        }

        $this->applyAccessScope($qb, $accessScope);

        return $qb;
    }

    public function countExcludingStatutBetween(
        string $excludedStatut,
        \DateTimeImmutable $from,
        \DateTimeImmutable $toExclusive,
    ): int {
        return (int) $this->createQueryBuilder('c')
            ->select('COUNT(c.id)')
            ->andWhere('c.consultedAt >= :from')
            ->andWhere('c.consultedAt < :to')
            ->andWhere('c.statut != :excludedStatut')
            ->setParameter('from', $from)
            ->setParameter('to', $toExclusive)
            ->setParameter('excludedStatut', $excludedStatut)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * @return array<string, int>
     */
    public function countExcludingStatutGroupedByDay(
        string $excludedStatut,
        \DateTimeImmutable $from,
        \DateTimeImmutable $toExclusive,
    ): array {
        $rows = $this->getEntityManager()->getConnection()->fetchAllAssociative(
            'SELECT DATE(consulted_at) AS day_key, COUNT(id) AS total
             FROM consultation
             WHERE consulted_at >= :from
               AND consulted_at < :to
               AND statut != :excludedStatut
             GROUP BY DATE(consulted_at)',
            [
                'from' => $from->format('Y-m-d H:i:s'),
                'to' => $toExclusive->format('Y-m-d H:i:s'),
                'excludedStatut' => $excludedStatut,
            ],
        );

        $map = [];
        foreach ($rows as $row) {
            $raw = $row['day_key'];
            $key = $raw instanceof \DateTimeInterface
                ? $raw->format('Y-m-d')
                : substr((string) $raw, 0, 10);
            $map[$key] = (int) $row['total'];
        }

        return $map;
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
