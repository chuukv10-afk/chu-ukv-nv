<?php

namespace App\Entity\Contract;

use App\Entity\Personnel;

interface BlameableInterface
{
    public function getCreatedAt(): ?\DateTimeImmutable;

    public function setCreatedAt(\DateTimeImmutable $createdAt): static;

    public function getUpdatedAt(): ?\DateTimeImmutable;

    public function setUpdatedAt(?\DateTimeImmutable $updatedAt): static;

    public function getCreatedBy(): ?Personnel;

    public function setCreatedBy(?Personnel $createdBy): static;

    public function getUpdatedBy(): ?Personnel;

    public function setUpdatedBy(?Personnel $updatedBy): static;
}
