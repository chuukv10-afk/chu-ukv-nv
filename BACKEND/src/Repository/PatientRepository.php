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
        ?int $filiereId = null,
        ?int $organisationId = null,
        ?string $typeInstitution = null,
    ): array {
        $qb = $this->createQueryBuilder('p')
            ->leftJoin('p.dpi', 'd')
            ->leftJoin('p.filiere', 'f')
            ->leftJoin('p.organisation', 'o')
            ->addSelect('d')
            ->addSelect('f')
            ->addSelect('o')
            ->orderBy('p.createdAt', 'DESC')
            ->distinct();

        $this->applyFilters($qb, $search, $status, $sexe, $filiereId, $organisationId, $typeInstitution);

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
    public function findForExport(
        ?string $search = null,
        ?string $status = null,
        ?string $sexe = null,
        ?int $filiereId = null,
        ?int $organisationId = null,
        ?string $typeInstitution = null,
    ): array {
        $qb = $this->createQueryBuilder('p')
            ->leftJoin('p.dpi', 'd')
            ->leftJoin('p.filiere', 'f')
            ->leftJoin('p.organisation', 'o')
            ->addSelect('d')
            ->addSelect('f')
            ->addSelect('o')
            ->orderBy('p.nom', 'ASC')
            ->addOrderBy('p.postNom', 'ASC');

        $this->applyFilters($qb, $search, $status, $sexe, $filiereId, $organisationId, $typeInstitution);

        return $qb->getQuery()->getResult();
    }

    public function findOneByCodeUkv(string $codeUkv): ?Patient
    {
        return $this->findOneBy(['codeUkv' => $codeUkv]);
    }

    public function findOneByIdentity(
        string $nom,
        string $postNom,
        ?string $prenom,
        \DateTimeInterface $dateNaissance,
    ): ?Patient {
        $qb = $this->createQueryBuilder('p')
            ->andWhere('UPPER(p.nom) = :nom')
            ->andWhere('UPPER(p.postNom) = :postNom')
            ->andWhere('p.dateNaissance = :dateNaissance')
            ->setParameter('nom', mb_strtoupper(trim($nom)))
            ->setParameter('postNom', mb_strtoupper(trim($postNom)))
            ->setParameter('dateNaissance', \DateTime::createFromInterface($dateNaissance)->setTime(0, 0))
            ->setMaxResults(1);

        $normalizedPrenom = null !== $prenom ? trim($prenom) : '';
        if ('' === $normalizedPrenom) {
            $qb->andWhere('p.prenom IS NULL OR TRIM(p.prenom) = \'\'');
        } else {
            $qb
                ->andWhere('UPPER(p.prenom) = :prenom')
                ->setParameter('prenom', mb_strtoupper($normalizedPrenom));
        }

        return $qb->getQuery()->getOneOrNullResult();
    }

    private function applyFilters(
        \Doctrine\ORM\QueryBuilder $qb,
        ?string $search,
        ?string $status,
        ?string $sexe,
        ?int $filiereId = null,
        ?int $organisationId = null,
        ?string $typeInstitution = null,
    ): void {
        $normalizedSearch = null !== $search ? trim($search) : '';
        if ('' !== $normalizedSearch) {
            $term = '%' . mb_strtolower($normalizedSearch) . '%';
            $clauses = [
                'LOWER(p.nom) LIKE :search',
                'LOWER(p.postNom) LIKE :search',
                'LOWER(p.prenom) LIKE :search',
                'p.telephone LIKE :search',
                'LOWER(d.numDossier) LIKE :search',
                'LOWER(p.codeUkv) LIKE :search',
                "LOWER(CONCAT(COALESCE(p.nom, ''), ' ', COALESCE(p.postNom, ''), ' ', COALESCE(p.prenom, ''))) LIKE :search",
                "LOWER(CONCAT(COALESCE(p.postNom, ''), ' ', COALESCE(p.nom, ''))) LIKE :search",
            ];

            $tokens = preg_split('/\s+/u', mb_strtolower($normalizedSearch), -1, PREG_SPLIT_NO_EMPTY) ?: [];
            if (count($tokens) > 1) {
                $tokenAnd = [];
                foreach ($tokens as $index => $token) {
                    $param = 'searchTok' . $index;
                    $tokenAnd[] = '(LOWER(p.nom) LIKE :' . $param
                        . ' OR LOWER(p.postNom) LIKE :' . $param
                        . ' OR LOWER(p.prenom) LIKE :' . $param
                        . ' OR LOWER(p.codeUkv) LIKE :' . $param
                        . ' OR LOWER(d.numDossier) LIKE :' . $param
                        . ' OR p.telephone LIKE :' . $param . ')';
                    $qb->setParameter($param, '%' . $token . '%');
                }
                $clauses[] = '(' . implode(' AND ', $tokenAnd) . ')';
            }

            $qb
                ->andWhere(implode(' OR ', $clauses))
                ->setParameter('search', $term);
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

        if (null !== $filiereId) {
            $qb
                ->andWhere('p.filiere = :filiereId')
                ->setParameter('filiereId', $filiereId);
        }

        if (null !== $organisationId) {
            $qb
                ->andWhere('p.organisation = :organisationId')
                ->setParameter('organisationId', $organisationId);
        }

        if (null !== $typeInstitution && '' !== trim($typeInstitution)) {
            $qb
                ->andWhere('o.typeInstitution = :typeInstitution')
                ->setParameter('typeInstitution', strtoupper(trim($typeInstitution)));
        }
    }

    public function countCreatedBetween(\DateTimeImmutable $from, \DateTimeImmutable $toExclusive): int
    {
        return (int) $this->createQueryBuilder('p')
            ->select('COUNT(p.id)')
            ->andWhere('p.createdAt >= :from')
            ->andWhere('p.createdAt < :to')
            ->setParameter('from', $from)
            ->setParameter('to', $toExclusive)
            ->getQuery()
            ->getSingleScalarResult();
    }
}
