<?php

namespace App\Entity;

use App\Repository\ActeFinancierVisiteRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ActeFinancierVisiteRepository::class)]
class ActeFinancierVisite
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column]
    private ?int $quantite = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 4)]
    private ?string $tarifUnitaire = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 4)]
    private ?string $tarifTotal = null;

    #[ORM\Column(length: 20)]
    private ?string $statut = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?ActeFinancier $acte = null;

    #[ORM\ManyToOne(inversedBy: 'acteFinancierVisites')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Visite $visite = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getQuantite(): ?int
    {
        return $this->quantite;
    }

    public function setQuantite(int $quantite): static
    {
        $this->quantite = $quantite;

        return $this;
    }

    public function getTarifUnitaire(): ?string
    {
        return $this->tarifUnitaire;
    }

    public function setTarifUnitaire(string $tarifUnitaire): static
    {
        $this->tarifUnitaire = $tarifUnitaire;

        return $this;
    }

    public function getTarifTotal(): ?string
    {
        return $this->tarifTotal;
    }

    public function setTarifTotal(string $tarifTotal): static
    {
        $this->tarifTotal = $tarifTotal;

        return $this;
    }

    public function getStatut(): ?string
    {
        return $this->statut;
    }

    public function setStatut(string $statut): static
    {
        $this->statut = $statut;

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

    public function getActe(): ?ActeFinancier
    {
        return $this->acte;
    }

    public function setActe(?ActeFinancier $acte): static
    {
        $this->acte = $acte;

        return $this;
    }

    public function getVisite(): ?Visite
    {
        return $this->visite;
    }

    public function setVisite(?Visite $visite): static
    {
        $this->visite = $visite;

        return $this;
    }
}
