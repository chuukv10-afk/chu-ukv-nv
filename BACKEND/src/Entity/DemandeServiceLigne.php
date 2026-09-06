<?php

namespace App\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
class DemandeServiceLigne
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'lignes')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?DemandeService $demande = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?Medicament $medicament = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true)]
    private ?Lot $lot = null;

    #[ORM\Column]
    private int $quantite = 0;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 4)]
    private string $prixUnitaire = '0.0000';

    #[ORM\Column(type: Types::DECIMAL, precision: 12, scale: 4)]
    private string $prixTotal = '0.0000';

    public function getId(): ?int { return $this->id; }
    public function getDemande(): ?DemandeService { return $this->demande; }
    public function setDemande(?DemandeService $demande): static { $this->demande = $demande; return $this; }
    public function getMedicament(): ?Medicament { return $this->medicament; }
    public function setMedicament(?Medicament $medicament): static { $this->medicament = $medicament; return $this; }
    public function getLot(): ?Lot { return $this->lot; }
    public function setLot(?Lot $lot): static { $this->lot = $lot; return $this; }
    public function getQuantite(): int { return $this->quantite; }
    public function setQuantite(int $quantite): static { $this->quantite = $quantite; return $this; }
    public function getPrixUnitaire(): string { return $this->prixUnitaire; }
    public function setPrixUnitaire(string $prixUnitaire): static { $this->prixUnitaire = $prixUnitaire; return $this; }
    public function getPrixTotal(): string { return $this->prixTotal; }
    public function setPrixTotal(string $prixTotal): static { $this->prixTotal = $prixTotal; return $this; }
}
