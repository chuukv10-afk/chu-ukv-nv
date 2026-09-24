<?php

namespace App\Repository;

use App\Entity\Facture;
use App\Repository\Trait\NumeroPrefixRepositoryTrait;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Facture>
 */
class FactureRepository extends ServiceEntityRepository
{
    use NumeroPrefixRepositoryTrait;

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Facture::class);
    }

    /**
     * @return array{items: list<Facture>, total: int}
     */
    public function searchPaginated(int $page, int $limit, array $filters): array
    {
        $qb = $this->createQueryBuilder('f')
            ->leftJoin('f.patient', 'p')->addSelect('p')
            ->leftJoin('p.dpi', 'd')->addSelect('d')
            ->leftJoin('f.structure', 's')->addSelect('s')
            ->leftJoin('f.createdBy', 'cb')->addSelect('cb');

        $this->applyFilters($qb, $filters);

        $countQb = clone $qb;
        $total = (int) $countQb
            ->select('COUNT(DISTINCT f.id)')
            ->resetDQLPart('orderBy')
            ->getQuery()
            ->getSingleScalarResult();

        $items = $qb
            ->orderBy('f.dateFacture', 'DESC')
            ->addOrderBy('f.id', 'DESC')
            ->setFirstResult(max(0, ($page - 1) * $limit))
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();

        return ['items' => $items, 'total' => $total];
    }

    private function applyFilters(QueryBuilder $qb, array $filters): void
    {
        $search = trim((string) ($filters['search'] ?? ''));
        if ('' !== $search) {
            $qb->andWhere(
                $qb->expr()->orX(
                    'f.numero LIKE :search',
                    'p.nom LIKE :search',
                    'p.postNom LIKE :search',
                    'p.prenom LIKE :search',
                    'p.codeUkv LIKE :search',
                    'd.numDossier LIKE :search',
                )
            )->setParameter('search', '%' . $search . '%');
        }

        $statut = trim((string) ($filters['statut'] ?? ''));
        if ('' !== $statut) {
            $qb->andWhere('f.statut = :statut')->setParameter('statut', $statut);
        }

        $categorie = trim((string) ($filters['categorieTarifaire'] ?? ''));
        if ('' !== $categorie) {
            $qb->andWhere('f.categorieTarifaire = :categorie')->setParameter('categorie', $categorie);
        }

        $structureId = (int) ($filters['structureId'] ?? 0);
        if ($structureId > 0) {
            $qb->andWhere('s.id = :structureId')->setParameter('structureId', $structureId);
        }

        $dateFrom = trim((string) ($filters['dateFrom'] ?? ''));
        if ('' !== $dateFrom) {
            $qb->andWhere('f.dateFacture >= :dateFrom')->setParameter('dateFrom', new \DateTimeImmutable($dateFrom));
        }

        $dateTo = trim((string) ($filters['dateTo'] ?? ''));
        if ('' !== $dateTo) {
            $qb->andWhere('f.dateFacture <= :dateTo')->setParameter('dateTo', new \DateTimeImmutable($dateTo));
        }
    }
}
