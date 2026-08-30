<?php

namespace App\Entity\Trait;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

trait UuidV7PrimaryKeyTrait
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    private ?Uuid $id = null;

    public function getId(): ?Uuid
    {
        return $this->id;
    }

    #[ORM\PrePersist]
    public function initializeUuidV7(): void
    {
        if (null === $this->id) {
            $this->id = Uuid::v7();
        }
    }
}
