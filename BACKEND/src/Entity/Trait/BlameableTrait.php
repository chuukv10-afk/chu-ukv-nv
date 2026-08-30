<?php

namespace App\Entity\Trait;

use App\Entity\Personnel;
use Doctrine\ORM\Mapping as ORM;

trait BlameableTrait
{
    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $updatedAt = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: 'created_by_id', nullable: true, onDelete: 'SET NULL')]
    private ?Personnel $createdBy = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: 'updated_by_id', nullable: true, onDelete: 'SET NULL')]
    private ?Personnel $updatedBy = null;

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeImmutable $createdAt): static
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    public function getUpdatedAt(): ?\DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(?\DateTimeImmutable $updatedAt): static
    {
        $this->updatedAt = $updatedAt;

        return $this;
    }

    public function getCreatedBy(): ?Personnel
    {
        return $this->createdBy;
    }

    public function setCreatedBy(?Personnel $createdBy): static
    {
        $this->createdBy = $createdBy;

        return $this;
    }

    public function getUpdatedBy(): ?Personnel
    {
        return $this->updatedBy;
    }

    public function setUpdatedBy(?Personnel $updatedBy): static
    {
        $this->updatedBy = $updatedBy;

        return $this;
    }
}
