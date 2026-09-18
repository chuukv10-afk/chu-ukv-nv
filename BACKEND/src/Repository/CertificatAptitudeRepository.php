<?php

namespace App\Repository;

use App\Entity\CertificatAptitude;
use App\Entity\Patient;
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
        ?int $filiereId = null,
        bool $sansFiliere = false,
        ?string $imprime = null,
    ): array {
        $qb = $this->createFilteredQueryBuilder($search, $annee, $statut, $verdict, $motif, $serviceId, $filiereId, $sansFiliere, $imprime);

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
        ?int $filiereId = null,
        bool $sansFiliere = false,
        ?string $imprime = null,
    ): array {
        return $this->createFilteredQueryBuilder($search, $annee, $statut, $verdict, $motif, $serviceId, $filiereId, $sansFiliere, $imprime)
            ->getQuery()
            ->getResult();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function countByFiliere(
        ?int $annee = null,
        ?string $statut = null,
        ?string $verdict = null,
        ?string $motif = null,
    ): array {
        $qb = $this->createQueryBuilder('c')
            ->leftJoin('c.filiere', 'f')
            ->select('IDENTITY(c.filiere) AS filiereId')
            ->addSelect('f.code AS filiereCode')
            ->addSelect('f.libelle AS filiereLibelle')
            ->addSelect('COUNT(c.id) AS total')
            ->addSelect('SUM(CASE WHEN c.verdict = :apte THEN 1 ELSE 0 END) AS apte')
            ->addSelect('SUM(CASE WHEN c.verdict = :inapte THEN 1 ELSE 0 END) AS inapte')
            ->addSelect('SUM(CASE WHEN c.statut = :brouillon THEN 1 ELSE 0 END) AS brouillon')
            ->addSelect('SUM(CASE WHEN c.statut = :signe THEN 1 ELSE 0 END) AS signe')
            ->addSelect('SUM(CASE WHEN c.statut = :annule THEN 1 ELSE 0 END) AS annule')
            ->setParameter('apte', CertificatAptitude::VERDICT_APTE)
            ->setParameter('inapte', CertificatAptitude::VERDICT_INAPTE)
            ->setParameter('brouillon', CertificatAptitude::STATUT_BROUILLON)
            ->setParameter('signe', CertificatAptitude::STATUT_SIGNE)
            ->setParameter('annule', CertificatAptitude::STATUT_ANNULE)
            ->groupBy('c.filiere')
            ->addGroupBy('f.code')
            ->addGroupBy('f.libelle')
            ->orderBy('f.libelle', 'ASC');

        if (null !== $annee && $annee > 0) {
            $qb->andWhere('c.annee = :annee')->setParameter('annee', $annee);
        }
        if (null !== $statut && '' !== trim($statut)) {
            $qb->andWhere('c.statut = :statutFilter')->setParameter('statutFilter', strtoupper(trim($statut)));
        }
        if (null !== $verdict && '' !== trim($verdict)) {
            $qb->andWhere('c.verdict = :verdictFilter')->setParameter('verdictFilter', strtoupper(trim($verdict)));
        }
        if (null !== $motif && '' !== trim($motif)) {
            $qb->andWhere('c.motif = :motif')->setParameter('motif', strtoupper(trim($motif)));
        }

        return $qb->getQuery()->getArrayResult();
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
        ?int $filiereId = null,
        bool $sansFiliere = false,
        ?string $imprime = null,
    ): \Doctrine\ORM\QueryBuilder {
        $qb = $this->createQueryBuilder('c')
            ->leftJoin('c.service', 's')->addSelect('s')
            ->leftJoin('c.patient', 'p')->addSelect('p')
            ->leftJoin('c.signePar', 'sp')->addSelect('sp')
            ->leftJoin('c.filiere', 'f')->addSelect('f')
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

        if ($sansFiliere) {
            $qb->andWhere('c.filiere IS NULL');
        } elseif (null !== $filiereId) {
            $qb->andWhere('f.id = :filiereId')->setParameter('filiereId', $filiereId);
        }

        if ('oui' === $imprime) {
            $qb->andWhere('c.imprime = TRUE');
        } elseif ('non' === $imprime) {
            $qb->andWhere('c.imprime = FALSE');
        }

        return $qb;
    }

    /**
     * @param list<int> $ids
     *
     * @return list<CertificatAptitude>
     */
    public function findOrderedByIds(array $ids): array
    {
        if ([] === $ids) {
            return [];
        }

        $items = $this->createQueryBuilder('c')
            ->leftJoin('c.service', 's')->addSelect('s')
            ->leftJoin('c.filiere', 'f')->addSelect('f')
            ->leftJoin('c.signePar', 'sp')->addSelect('sp')
            ->andWhere('c.id IN (:ids)')
            ->setParameter('ids', $ids)
            ->getQuery()
            ->getResult();

        $byId = [];
        foreach ($items as $item) {
            $byId[(int) $item->getId()] = $item;
        }

        $ordered = [];
        foreach ($ids as $id) {
            if (isset($byId[$id])) {
                $ordered[] = $byId[$id];
            }
        }

        return $ordered;
    }

    public function findActiveAdmissionForPatient(Patient $patient, int $annee): ?CertificatAptitude
    {
        return $this->createQueryBuilder('c')
            ->andWhere('c.patient = :patient')
            ->andWhere('c.annee = :annee')
            ->andWhere('c.motif = :motif')
            ->andWhere('c.statut IN (:statuts)')
            ->setParameter('patient', $patient)
            ->setParameter('annee', $annee)
            ->setParameter('motif', CertificatAptitude::MOTIF_ADMISSION_UKV)
            ->setParameter('statuts', [
                CertificatAptitude::STATUT_BROUILLON,
                CertificatAptitude::STATUT_SIGNE,
            ])
            ->orderBy('c.id', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function findOfficialByNumero(string $numero): ?CertificatAptitude
    {
        $normalized = preg_replace('/\s+/', ' ', trim($numero)) ?? '';
        if ('' === $normalized) {
            return null;
        }

        $compact = strtoupper(str_replace(' ', '', $normalized));

        return $this->createQueryBuilder('c')
            ->leftJoin('c.signePar', 'p')
            ->addSelect('p')
            ->andWhere('c.statut IN (:statuts)')
            ->andWhere("REPLACE(UPPER(COALESCE(c.numero, '')), ' ', '') = :compact")
            ->setParameter('statuts', [
                CertificatAptitude::STATUT_SIGNE,
                CertificatAptitude::STATUT_ANNULE,
            ])
            ->setParameter('compact', $compact)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
