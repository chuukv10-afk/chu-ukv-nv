<?php

namespace App\Repository;

use App\Entity\EtudeImagerie;
use App\Repository\Trait\NumeroPrefixRepositoryTrait;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<EtudeImagerie>
 */
class EtudeImagerieRepository extends ServiceEntityRepository
{
    use NumeroPrefixRepositoryTrait;

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, EtudeImagerie::class);
    }

    /**
     * @return array{items: list<EtudeImagerie>, total: int}
     */
    public function paginate(int $page, int $limit, ?string $search, ?string $statut): array
    {
        $qb = $this->createQueryBuilder('e')
            ->leftJoin('e.patient', 'p')->addSelect('p')
            ->leftJoin('e.examen', 'x')->addSelect('x')
            ->leftJoin('x.typeExamen', 't')->addSelect('t')
            ->orderBy('e.createdAt', 'DESC');

        $normalizedSearch = null !== $search ? trim($search) : '';
        if ('' !== $normalizedSearch) {
            $qb
                ->andWhere('LOWER(e.numero) LIKE :search OR LOWER(p.nom) LIKE :search OR LOWER(p.postNom) LIKE :search OR LOWER(p.prenom) LIKE :search OR LOWER(x.libelle) LIKE :search')
                ->setParameter('search', '%' . mb_strtolower($normalizedSearch) . '%');
        }
        if (null !== $statut && '' !== trim($statut)) {
            $qb->andWhere('e.statut = :statut')->setParameter('statut', strtoupper(trim($statut)));
        }

        $countQb = clone $qb;
        $total = (int) $countQb->select('COUNT(e.id)')->resetDQLPart('orderBy')->getQuery()->getSingleScalarResult();

        $items = $qb
            ->setFirstResult(max(0, ($page - 1) * $limit))
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();

        return ['items' => $items, 'total' => $total];
    }
}
