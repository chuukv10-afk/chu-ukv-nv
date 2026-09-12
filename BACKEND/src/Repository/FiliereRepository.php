<?php

namespace App\Repository;

use App\Entity\Filiere;
use App\Repository\Trait\CodeLibellePaginateTrait;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Filiere>
 */
class FiliereRepository extends ServiceEntityRepository
{
    use CodeLibellePaginateTrait;

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Filiere::class);
    }

    /**
     * @return array{items: list<Filiere>, total: int}
     */
    public function paginate(int $page, int $limit, ?string $search = null): array
    {
        return $this->paginateByCodeLibelle('f', $page, $limit, $search);
    }

    /**
     * @return list<Filiere>
     */
    public function findAllOrdered(): array
    {
        return $this->findBy([], ['libelle' => 'ASC']);
    }
}
