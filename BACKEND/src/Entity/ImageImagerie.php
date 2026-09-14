<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
class ImageImagerie
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'images')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?EtudeImagerie $etude = null;

    #[ORM\Column(length: 180)]
    private ?string $storageKey = null;

    #[ORM\Column(length: 180)]
    private ?string $originalName = null;

    #[ORM\Column(length: 80)]
    private ?string $mimeType = null;

    #[ORM\Column]
    private int $sizeBytes = 0;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Personnel $uploadedBy = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $uploadedAt = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getEtude(): ?EtudeImagerie
    {
        return $this->etude;
    }

    public function setEtude(?EtudeImagerie $etude): static
    {
        $this->etude = $etude;

        return $this;
    }

    public function getStorageKey(): ?string
    {
        return $this->storageKey;
    }

    public function setStorageKey(string $storageKey): static
    {
        $this->storageKey = $storageKey;

        return $this;
    }

    public function getOriginalName(): ?string
    {
        return $this->originalName;
    }

    public function setOriginalName(string $originalName): static
    {
        $this->originalName = $originalName;

        return $this;
    }

    public function getMimeType(): ?string
    {
        return $this->mimeType;
    }

    public function setMimeType(string $mimeType): static
    {
        $this->mimeType = $mimeType;

        return $this;
    }

    public function getSizeBytes(): int
    {
        return $this->sizeBytes;
    }

    public function setSizeBytes(int $sizeBytes): static
    {
        $this->sizeBytes = $sizeBytes;

        return $this;
    }

    public function getUploadedBy(): ?Personnel
    {
        return $this->uploadedBy;
    }

    public function setUploadedBy(?Personnel $uploadedBy): static
    {
        $this->uploadedBy = $uploadedBy;

        return $this;
    }

    public function getUploadedAt(): ?\DateTimeImmutable
    {
        return $this->uploadedAt;
    }

    public function setUploadedAt(\DateTimeImmutable $uploadedAt): static
    {
        $this->uploadedAt = $uploadedAt;

        return $this;
    }
}
