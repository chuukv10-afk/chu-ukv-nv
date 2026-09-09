<?php

namespace App\Repository;

use App\DTO\Intendance\BienListQuery;
use App\Entity\BienPatrimonial;
use App\Entity\FamilleBien;
use App\Entity\LocalIntendance;
use App\Entity\TypeBien;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<BienPatrimonial>
 */
class BienPatrimonialRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, BienPatrimonial::class);
    }

    /**
     * @return array{items: list<BienPatrimonial>, total: int}
     */
    public function paginate(BienListQuery $query): array
    {
        $qb = $this->filteredQuery($query)
            ->orderBy('b.codeInventaire', 'ASC');

        $countQb = clone $qb;
        $total = (int) $countQb->select('COUNT(b.id)')->getQuery()->getSingleScalarResult();

        $items = $qb
            ->setFirstResult(max(0, ($query->page - 1) * $query->limit))
            ->setMaxResults($query->limit)
            ->getQuery()
            ->getResult();

        return ['items' => $items, 'total' => $total];
    }

    /**
     * @return list<BienPatrimonial>
     */
    public function findFiltered(BienListQuery $query): array
    {
        return $this->filteredQuery($query)
            ->orderBy('b.codeInventaire', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function findByCodeInventaire(string $code): ?BienPatrimonial
    {
        return $this->createQueryBuilder('b')
            ->andWhere('UPPER(b.codeInventaire) = :code')
            ->andWhere('b.supprimeAt IS NULL')
            ->setParameter('code', strtoupper(trim($code)))
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * @param list<int> $ids
     * @return list<BienPatrimonial>
     */
    public function findActifsByIds(array $ids): array
    {
        if ([] === $ids) {
            return [];
        }

        /** @var list<BienPatrimonial> $items */
        $items = $this->createQueryBuilder('b')
            ->leftJoin('b.type', 't')->addSelect('t')
            ->leftJoin('b.famille', 'f')->addSelect('f')
            ->leftJoin('b.service', 's')->addSelect('s')
            ->leftJoin('b.local', 'l')->addSelect('l')
            ->andWhere('b.id IN (:ids)')
            ->andWhere('b.supprimeAt IS NULL')
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

    /**
     * @return list<string>
     */
    public function findCodesWithPrefix(string $prefix): array
    {
        $rows = $this->createQueryBuilder('b')
            ->select('b.codeInventaire')
            ->andWhere('b.codeInventaire LIKE :prefix')
            ->setParameter('prefix', $prefix . '%')
            ->getQuery()
            ->getSingleColumnResult();

        return array_map(static fn (mixed $code): string => (string) $code, $rows);
    }

    public function countByType(TypeBien $type): int
    {
        return (int) $this->createQueryBuilder('b')
            ->select('COUNT(b.id)')
            ->andWhere('b.type = :type')
            ->andWhere('b.supprimeAt IS NULL')
            ->setParameter('type', $type)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function countByFamille(FamilleBien $famille): int
    {
        return (int) $this->createQueryBuilder('b')
            ->select('COUNT(b.id)')
            ->andWhere('b.famille = :famille')
            ->andWhere('b.supprimeAt IS NULL')
            ->setParameter('famille', $famille)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function countByLocal(LocalIntendance $local): int
    {
        return (int) $this->createQueryBuilder('b')
            ->select('COUNT(b.id)')
            ->andWhere('b.local = :local')
            ->andWhere('b.supprimeAt IS NULL')
            ->setParameter('local', $local)
            ->getQuery()
            ->getSingleScalarResult();
    }

    private function filteredQuery(BienListQuery $query): \Doctrine\ORM\QueryBuilder
    {
        $qb = $this->createQueryBuilder('b')
            ->leftJoin('b.type', 't')->addSelect('t')
            ->leftJoin('b.famille', 'f')->addSelect('f')
            ->leftJoin('b.service', 's')->addSelect('s')
            ->leftJoin('b.local', 'l')->addSelect('l');

        $qb->andWhere('b.supprimeAt IS NULL');

        if (!$query->includeReformes) {
            $qb->andWhere('b.etat <> :reforme')->setParameter('reforme', BienPatrimonial::ETAT_R);
        }

        $normalizedSearch = null !== $query->search ? trim($query->search) : '';
        if ('' !== $normalizedSearch) {
            $qb
                ->andWhere('LOWER(b.codeInventaire) LIKE :search OR LOWER(t.libelle) LIKE :search OR LOWER(b.marque) LIKE :search OR LOWER(b.numeroSerie) LIKE :search')
                ->setParameter('search', '%' . mb_strtolower($normalizedSearch) . '%');
        }

        if (null !== $query->serviceId) {
            $qb->andWhere('s.id = :serviceId')->setParameter('serviceId', $query->serviceId);
        }
        if (null !== $query->localId) {
            $qb->andWhere('l.id = :localId')->setParameter('localId', $query->localId);
        }
        if ($query->sansLocal) {
            $qb->andWhere('b.local IS NULL');
        }
        if (null !== $query->typeId) {
            $qb->andWhere('t.id = :typeId')->setParameter('typeId', $query->typeId);
        }
        if (null !== $query->familleId) {
            $qb->andWhere('f.id = :familleId')->setParameter('familleId', $query->familleId);
        }
        if (null !== $query->etat && '' !== $query->etat) {
            $qb->andWhere('b.etat = :etat')->setParameter('etat', $query->etat);
        }

        return $qb;
    }
}
