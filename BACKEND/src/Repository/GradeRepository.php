<?php

namespace App\Repository;

use App\Entity\Grade;
use App\Repository\Trait\CodeLibellePaginateTrait;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Grade>
 */
class GradeRepository extends ServiceEntityRepository
{
    use CodeLibellePaginateTrait;

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Grade::class);
    }

    /**
     * @return array{items: list<Grade>, total: int}
     */
    public function paginate(int $page, int $limit, ?string $search = null): array
    {
        return $this->paginateByCodeLibelle('g', $page, $limit, $search);
    }
}

