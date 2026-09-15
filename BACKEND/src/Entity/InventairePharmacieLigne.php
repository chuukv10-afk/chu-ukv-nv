<?php

namespace App\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\UniqueConstraint(name: 'UNIQ_INV_PHARMA_LIGNE_LOT', columns: ['inventaire_id', 'lot_id'])]
class InventairePharmacieLigne
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'lignes')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?InventairePharmacie $inventaire = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?Lot $lot = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?Medicament $medicament = null;

    #[ORM\Column(length: 40)]
    private ?string $numeroLot = null;

    #[ORM\Column(type: Types::DATE_IMMUTABLE)]
    private ?\DateTimeImmutable $datePeremption = null;

    #[ORM\Column]
    private int $quantiteSysteme = 0;

    #[ORM\Column(nullable: true)]
    private ?int $quantiteComptee = null;

    #[ORM\Column]
    private bool $compte = false;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $compteAt = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: 'compte_par_id', nullable: true, onDelete: 'SET NULL')]
    private ?Personnel $comptePar = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?MouvementStock $mouvement = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getInventaire(): ?InventairePharmacie
    {
        return $this->inventaire;
    }

    public function setInventaire(?InventairePharmacie $inventaire): static
    {
        $this->inventaire = $inventaire;

        return $this;
    }

    public function getLot(): ?Lot
    {
        return $this->lot;
    }

    public function setLot(?Lot $lot): static
    {
        $this->lot = $lot;

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

    public function getQuantiteSysteme(): int
    {
        return $this->quantiteSysteme;
    }

    public function setQuantiteSysteme(int $quantiteSysteme): static
    {
        $this->quantiteSysteme = $quantiteSysteme;

        return $this;
    }

    public function getQuantiteComptee(): ?int
    {
        return $this->quantiteComptee;
    }

    public function setQuantiteComptee(?int $quantiteComptee): static
    {
        $this->quantiteComptee = $quantiteComptee;

        return $this;
    }

    public function isCompte(): bool
    {
        return $this->compte;
    }

    public function setCompte(bool $compte): static
    {
        $this->compte = $compte;

        return $this;
    }

    public function getCompteAt(): ?\DateTimeImmutable
    {
        return $this->compteAt;
    }

    public function setCompteAt(?\DateTimeImmutable $compteAt): static
    {
        $this->compteAt = $compteAt;

        return $this;
    }

    public function getComptePar(): ?Personnel
    {
        return $this->comptePar;
    }

    public function setComptePar(?Personnel $comptePar): static
    {
        $this->comptePar = $comptePar;

        return $this;
    }

    public function getMouvement(): ?MouvementStock
    {
        return $this->mouvement;
    }

    public function setMouvement(?MouvementStock $mouvement): static
    {
        $this->mouvement = $mouvement;

        return $this;
    }
}
