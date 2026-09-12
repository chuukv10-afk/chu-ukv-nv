<?php

namespace App\Repository;

use App\Entity\Lot;
use App\Entity\Medicament;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Lot>
 */
class LotRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Lot::class);
    }

    /**
     * @return array{items: list<Lot>, total: int}
     */
    public function paginate(int $page, int $limit, ?string $search = null, ?string $statut = null, ?int $medicamentId = null): array
    {
        $qb = $this->createQueryBuilder('l')
            ->leftJoin('l.medicament', 'm')->addSelect('m')
            ->orderBy('l.datePeremption', 'ASC');

        $normalizedSearch = null !== $search ? trim($search) : '';
        if ('' !== $normalizedSearch) {
            $qb
                ->andWhere('LOWER(l.numeroLot) LIKE :search OR LOWER(m.code) LIKE :search OR LOWER(m.libelle) LIKE :search')
                ->setParameter('search', '%' . mb_strtolower($normalizedSearch) . '%');
        }

        if (null !== $statut && '' !== $statut) {
            $qb->andWhere('l.statut = :statut')->setParameter('statut', $statut);
        }

        if (null !== $medicamentId && $medicamentId > 0) {
            $qb->andWhere('m.id = :medicamentId')->setParameter('medicamentId', $medicamentId);
        }

        $countQb = clone $qb;
        $total = (int) $countQb->select('COUNT(l.id)')->resetDQLPart('orderBy')->getQuery()->getSingleScalarResult();

        $items = $qb
            ->setFirstResult(max(0, ($page - 1) * $limit))
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();

        return ['items' => $items, 'total' => $total];
    }

    public function findOneByMedicamentAndNumero(Medicament $medicament, string $numeroLot): ?Lot
    {
        return $this->findOneBy([
            'medicament' => $medicament,
            'numeroLot' => $numeroLot,
        ]);
    }

    /**
     * @return list<Lot>
     */
    public function findVendables(Medicament $medicament, \DateTimeImmutable $today): array
    {
        return $this->createQueryBuilder('l')
            ->andWhere('l.medicament = :medicament')
            ->andWhere('l.statut = :statut')
            ->andWhere('l.quantiteRestante > 0')
            ->andWhere('l.datePeremption >= :today')
            ->setParameter('medicament', $medicament)
            ->setParameter('statut', Lot::STATUT_DISPONIBLE)
            ->setParameter('today', $today->format('Y-m-d'))
            ->orderBy('l.datePeremption', 'ASC')
            ->addOrderBy('l.id', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Lots avec reste, y compris périmés (saisie antérieure). Exclut les lots bloqués.
     *
     * @return list<Lot>
     */
    public function findAvecReste(Medicament $medicament): array
    {
        return $this->createQueryBuilder('l')
            ->andWhere('l.medicament = :medicament')
            ->andWhere('l.statut != :bloque')
            ->andWhere('l.quantiteRestante > 0')
            ->setParameter('medicament', $medicament)
            ->setParameter('bloque', Lot::STATUT_BLOQUE)
            ->orderBy('l.datePeremption', 'ASC')
            ->addOrderBy('l.id', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function stockRestant(Medicament $medicament): int
    {
        return (int) $this->createQueryBuilder('l')
            ->select('COALESCE(SUM(l.quantiteRestante), 0)')
            ->andWhere('l.medicament = :medicament')
            ->andWhere('l.statut != :bloque')
            ->andWhere('l.quantiteRestante > 0')
            ->setParameter('medicament', $medicament)
            ->setParameter('bloque', Lot::STATUT_BLOQUE)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function stockDisponible(Medicament $medicament, \DateTimeImmutable $today): int
    {
        return (int) $this->createQueryBuilder('l')
            ->select('COALESCE(SUM(l.quantiteRestante), 0)')
            ->andWhere('l.medicament = :medicament')
            ->andWhere('l.statut = :statut')
            ->andWhere('l.quantiteRestante > 0')
            ->andWhere('l.datePeremption >= :today')
            ->setParameter('medicament', $medicament)
            ->setParameter('statut', Lot::STATUT_DISPONIBLE)
            ->setParameter('today', $today->format('Y-m-d'))
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function valeurStock(): string
    {
        $total = $this->createQueryBuilder('l')
            ->select('COALESCE(SUM(l.quantiteRestante * l.prixAchatUnitaire), 0)')
            ->andWhere('l.quantiteRestante > 0')
            ->getQuery()
            ->getSingleScalarResult();

        return number_format((float) $total, 4, '.', '');
    }

    public function countByMedicament(Medicament $medicament): int
    {
        return (int) $this->createQueryBuilder('l')
            ->select('COUNT(l.id)')
            ->andWhere('l.medicament = :medicament')
            ->setParameter('medicament', $medicament)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * @return list<Lot>
     */
    public function findPerimesOuProches(\DateTimeImmutable $today, \DateTimeImmutable $horizon): array
    {
        return $this->createQueryBuilder('l')
            ->leftJoin('l.medicament', 'm')->addSelect('m')
            ->andWhere('l.quantiteRestante > 0')
            ->andWhere('l.datePeremption <= :horizon')
            ->setParameter('horizon', $horizon->format('Y-m-d'))
            ->orderBy('l.datePeremption', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
