<?php

namespace App\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
class ReceptionLigne
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'lignes')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Reception $reception = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?Medicament $medicament = null;

    #[ORM\Column(length: 40)]
    private ?string $numeroLot = null;

    #[ORM\Column(type: Types::DATE_IMMUTABLE)]
    private ?\DateTimeImmutable $datePeremption = null;

    #[ORM\Column]
    private int $quantite = 0;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 4)]
    private ?string $prixAchatUnitaire = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getReception(): ?Reception
    {
        return $this->reception;
    }

    public function setReception(?Reception $reception): static
    {
        $this->reception = $reception;

        return $this;
    }

    public function getMedicament(): ?Medicament
    {
        return $this->medicament;
    }

    public function setMedicament(?Medicament $medicament): static
    {
        $this->medicament = $medicament;

        return $this;
    }

    public function getNumeroLot(): ?string
    {
        return $this->numeroLot;
    }

    public function setNumeroLot(string $numeroLot): static
    {
        $this->numeroLot = $numeroLot;

        return $this;
    }

    public function getDatePeremption(): ?\DateTimeImmutable
    {
        return $this->datePeremption;
    }

    public function setDatePeremption(\DateTimeImmutable $datePeremption): static
    {
        $this->datePeremption = $datePeremption;

        return $this;
    }

    public function getQuantite(): int
    {
        return $this->quantite;
    }

    public function setQuantite(int $quantite): static
    {
        $this->quantite = $quantite;

        return $this;
    }

    public function getPrixAchatUnitaire(): ?string
    {
        return $this->prixAchatUnitaire;
    }

    public function setPrixAchatUnitaire(string $prixAchatUnitaire): static
    {
        $this->prixAchatUnitaire = $prixAchatUnitaire;

        return $this;
    }
}
