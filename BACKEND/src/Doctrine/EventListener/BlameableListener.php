<?php

namespace App\Doctrine\EventListener;

use App\Entity\Contract\BlameableInterface;
use App\Entity\Personnel;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\ORM\Event\PrePersistEventArgs;
use Doctrine\ORM\Event\PreUpdateEventArgs;
use Doctrine\ORM\Events;
use Symfony\Bundle\SecurityBundle\Security;

#[AsDoctrineListener(event: Events::prePersist)]
#[AsDoctrineListener(event: Events::preUpdate)]
final class BlameableListener
{
    public function __construct(
        private readonly Security $security,
    ) {
    }

    public function prePersist(PrePersistEventArgs $args): void
    {
        $entity = $args->getObject();
        if (!$entity instanceof BlameableInterface) {
            return;
        }

        if (null === $entity->getCreatedAt()) {
            $entity->setCreatedAt(new \DateTimeImmutable());
        }

        $personnel = $this->getCurrentPersonnel();
        if (null !== $personnel && null === $entity->getCreatedBy()) {
            $entity->setCreatedBy($personnel);
        }
    }

    public function preUpdate(PreUpdateEventArgs $args): void
    {
        $entity = $args->getObject();
        if (!$entity instanceof BlameableInterface) {
            return;
        }

        $entity->setUpdatedAt(new \DateTimeImmutable());

        $personnel = $this->getCurrentPersonnel();
        if (null !== $personnel) {
            $entity->setUpdatedBy($personnel);
        }
    }

    private function getCurrentPersonnel(): ?Personnel
    {
        $user = $this->security->getUser();

        return $user instanceof Personnel ? $user : null;
    }
}
