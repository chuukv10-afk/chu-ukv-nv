<?php

namespace App\Repository;

use App\Entity\InventairePharmacie;
use App\Repository\Trait\NumeroPrefixRepositoryTrait;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<InventairePharmacie>
 */
class InventairePharmacieRepository extends ServiceEntityRepository
{
    use NumeroPrefixRepositoryTrait;

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, InventairePharmacie::class);
    }

    /**
     * @return array{items: list<InventairePharmacie>, total: int}
     */
    public function paginate(int $page, int $limit, ?string $search = null, ?string $statut = null): array
    {
        $qb = $this->createQueryBuilder('i')
            ->orderBy('i.createdAt', 'DESC');

        $normalizedSearch = null !== $search ? trim($search) : '';
        if ('' !== $normalizedSearch) {
            $qb
                ->andWhere('LOWER(i.numero) LIKE :search OR LOWER(i.libelle) LIKE :search')
                ->setParameter('search', '%' . mb_strtolower($normalizedSearch) . '%');
        }

        if (null !== $statut && '' !== $statut) {
            $qb->andWhere('i.statut = :statut')->setParameter('statut', $statut);
        }

        $countQb = clone $qb;
        $total = (int) $countQb->select('COUNT(i.id)')->resetDQLPart('orderBy')->getQuery()->getSingleScalarResult();

        $items = $qb
            ->setFirstResult(max(0, ($page - 1) * $limit))
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();

        return ['items' => $items, 'total' => $total];
    }

    public function findEnCours(): ?InventairePharmacie
    {
        return $this->findOneBy(['statut' => InventairePharmacie::STATUT_EN_COURS]);
    }

    public function findWithLignes(int $id): ?InventairePharmacie
    {
        return $this->createQueryBuilder('i')
            ->leftJoin('i.lignes', 'l')->addSelect('l')
            ->leftJoin('l.medicament', 'm')->addSelect('m')
            ->leftJoin('m.unite', 'u')->addSelect('u')
            ->leftJoin('l.lot', 'lot')->addSelect('lot')
            ->leftJoin('l.comptePar', 'p')->addSelect('p')
            ->leftJoin('l.mouvement', 'mv')->addSelect('mv')
            ->andWhere('i.id = :id')
            ->setParameter('id', $id)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
