<?php

namespace App\Repository;

use App\Entity\DemandeService;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<DemandeService>
 */
class DemandeServiceRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, DemandeService::class);
    }

    /**
     * @return array{items: list<DemandeService>, total: int}
     */
    public function paginate(
        int $page,
        int $limit,
        ?string $search = null,
        ?string $statut = null,
        ?string $statutPaiement = null,
    ): array {
        $qb = $this->createQueryBuilder('d')
            ->leftJoin('d.service', 's')->addSelect('s')
            ->orderBy('d.createdAt', 'DESC');

        $normalizedSearch = null !== $search ? trim($search) : '';
        if ('' !== $normalizedSearch) {
            $qb
                ->andWhere('LOWER(d.numero) LIKE :search OR LOWER(s.libelle) LIKE :search')
                ->setParameter('search', '%' . mb_strtolower($normalizedSearch) . '%');
        }
        if (null !== $statut && '' !== $statut) {
            $qb->andWhere('d.statut = :statut')->setParameter('statut', $statut);
        }
        if (null !== $statutPaiement && '' !== $statutPaiement) {
            $qb->andWhere('d.statutPaiement = :statutPaiement')->setParameter('statutPaiement', $statutPaiement);
        }

        $countQb = clone $qb;
        $total = (int) $countQb->select('COUNT(d.id)')->resetDQLPart('orderBy')->getQuery()->getSingleScalarResult();
        $items = $qb->setFirstResult(max(0, ($page - 1) * $limit))->setMaxResults($limit)->getQuery()->getResult();

        return ['items' => $items, 'total' => $total];
    }

    /**
     * @return list<DemandeService>
     */
    public function findForRecettes(?string $dateFrom, ?string $dateTo, ?string $search = null): array
    {
        $qb = $this->createQueryBuilder('d')
            ->leftJoin('d.service', 's')->addSelect('s')
            ->leftJoin('d.visite', 'v')->addSelect('v')
            ->leftJoin('v.dpi', 'dpi')->addSelect('dpi')
            ->leftJoin('dpi.patient', 'p')->addSelect('p')
            ->andWhere('d.statut = :statut')
            ->andWhere('d.statutPaiement IN (:paiements)')
            ->setParameter('statut', DemandeService::STATUT_DELIVREE)
            ->setParameter('paiements', [DemandeService::PAIEMENT_PAYEE, DemandeService::PAIEMENT_IMPAYEE]);

        if (null !== $dateFrom && '' !== $dateFrom) {
            $qb
                ->andWhere('COALESCE(d.payeAt, d.delivreeAt, d.updatedAt, d.createdAt) >= :dateFrom')
                ->setParameter('dateFrom', new \DateTimeImmutable($dateFrom . ' 00:00:00'));
        }
        if (null !== $dateTo && '' !== $dateTo) {
            $qb
                ->andWhere('COALESCE(d.payeAt, d.delivreeAt, d.updatedAt, d.createdAt) <= :dateTo')
                ->setParameter('dateTo', new \DateTimeImmutable($dateTo . ' 23:59:59'));
        }

        $normalizedSearch = null !== $search ? trim($search) : '';
        if ('' !== $normalizedSearch) {
            $qb
                ->andWhere('LOWER(d.numero) LIKE :search OR LOWER(s.libelle) LIKE :search OR LOWER(s.code) LIKE :search OR LOWER(p.nom) LIKE :search OR LOWER(p.postNom) LIKE :search OR LOWER(p.prenom) LIKE :search')
                ->setParameter('search', '%' . mb_strtolower($normalizedSearch) . '%');
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * @return list<DemandeService>
     */
    public function findDelivreesForStats(?string $dateFrom, ?string $dateTo): array
    {
        $qb = $this->createQueryBuilder('d')
            ->andWhere('d.statut = :statut')
            ->setParameter('statut', DemandeService::STATUT_DELIVREE);

        if (null !== $dateFrom && '' !== $dateFrom) {
            $qb
                ->andWhere('d.delivreeAt >= :from OR d.payeAt >= :from OR d.createdAt >= :from')
                ->setParameter('from', new \DateTimeImmutable($dateFrom . ' 00:00:00'));
        }
        if (null !== $dateTo && '' !== $dateTo) {
            $qb
                ->andWhere('d.delivreeAt <= :to OR d.payeAt <= :to OR d.createdAt <= :to')
                ->setParameter('to', new \DateTimeImmutable($dateTo . ' 23:59:59'));
        }

        return $qb->getQuery()->getResult();
    }

    public function sumCreancesOuvertes(): string
    {
        $total = $this->createQueryBuilder('d')
            ->select('COALESCE(SUM(d.montantTotal), 0)')
            ->andWhere('d.statut = :statut')
            ->andWhere('d.statutPaiement = :paiement')
            ->setParameter('statut', DemandeService::STATUT_DELIVREE)
            ->setParameter('paiement', DemandeService::PAIEMENT_IMPAYEE)
            ->getQuery()
            ->getSingleScalarResult();

        return number_format((float) $total, 4, '.', '');
    }

    public function countCreancesOuvertes(): int
    {
        return (int) $this->createQueryBuilder('d')
            ->select('COUNT(d.id)')
            ->andWhere('d.statut = :statut')
            ->andWhere('d.statutPaiement = :paiement')
            ->setParameter('statut', DemandeService::STATUT_DELIVREE)
            ->setParameter('paiement', DemandeService::PAIEMENT_IMPAYEE)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function countNumeroPrefix(string $prefix): int
    {
        return (int) $this->createQueryBuilder('d')
            ->select('COUNT(d.id)')
            ->andWhere('d.numero LIKE :prefix')
            ->setParameter('prefix', $prefix . '%')
            ->getQuery()
            ->getSingleScalarResult();
    }
}
