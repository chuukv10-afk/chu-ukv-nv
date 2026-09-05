<?php

namespace App\Repository;

use App\Entity\TypeExamen;
use App\Repository\Trait\CodeLibellePaginateTrait;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<TypeExamen>
 */
class TypeExamenRepository extends ServiceEntityRepository
{
    use CodeLibellePaginateTrait;

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, TypeExamen::class);
    }

    /**
     * @return array{items: list<TypeExamen>, total: int}
     */
    public function paginate(int $page, int $limit, ?string $search = null): array
    {
        return $this->paginateByCodeLibelle('t', $page, $limit, $search);
    }

    /**
     * @return list<TypeExamen>
     */
    public function findForExport(?string $search = null): array
    {
        return $this->findAllByCodeLibelle('t', $search);
    }
}
