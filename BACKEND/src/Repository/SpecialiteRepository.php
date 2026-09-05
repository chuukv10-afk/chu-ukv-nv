<?php

namespace App\Repository;

use App\Entity\Specialite;
use App\Repository\Trait\CodeLibellePaginateTrait;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Specialite>
 */
class SpecialiteRepository extends ServiceEntityRepository
{
    use CodeLibellePaginateTrait;

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Specialite::class);
    }

    /**
     * @return array{items: list<Specialite>, total: int}
     */
    public function paginate(int $page, int $limit, ?string $search = null): array
    {
        return $this->paginateByCodeLibelle('s', $page, $limit, $search);
    }

    /**
     * @return list<Specialite>
     */
    public function findForExport(?string $search = null): array
    {
        return $this->findAllByCodeLibelle('s', $search);
    }
}
