<?php

namespace App\Entity;

use App\Repository\AntecedentRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: AntecedentRepository::class)]
class Antecedent
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?TypeAntecedent $type = null;

    #[ORM\ManyToOne(inversedBy: 'dpi')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Maladie $maladie = null;

    #[ORM\ManyToOne(inversedBy: 'antecedents')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Dpi $dpi = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getType(): ?TypeAntecedent
    {
        return $this->type;
    }

    public function setType(?TypeAntecedent $type): static
    {
        $this->type = $type;

        return $this;
    }

    public function getMaladie(): ?Maladie
    {
        return $this->maladie;
    }

    public function setMaladie(?Maladie $maladie): static
    {
        $this->maladie = $maladie;

        return $this;
    }

    public function getDpi(): ?Dpi
    {
        return $this->dpi;
    }

    public function setDpi(?Dpi $dpi): static
    {
        $this->dpi = $dpi;

        return $this;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeImmutable $createdAt): static
    {
        $this->createdAt = $createdAt;

        return $this;
    }
}
