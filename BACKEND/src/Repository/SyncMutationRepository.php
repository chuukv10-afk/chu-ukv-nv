<?php

namespace App\Repository;

use App\Entity\SyncMutation;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<SyncMutation>
 */
class SyncMutationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, SyncMutation::class);
    }

    public function findOneByClientId(string $clientId): ?SyncMutation
    {
        return $this->findOneBy(['clientId' => $clientId]);
    }
}
