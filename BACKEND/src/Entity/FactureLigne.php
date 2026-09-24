<?php

namespace App\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'facture_ligne')]
class FactureLigne
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'lignes')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Facture $facture = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?ActeFinancier $acte = null;

    #[ORM\Column(length: 40)]
    private string $codeActe = '';

    #[ORM\Column(length: 255)]
    private string $libelle = '';

    #[ORM\Column(length: 80, nullable: true)]
    private ?string $serviceGrille = null;

    #[ORM\Column]
    private int $quantite = 1;

    #[ORM\Column(type: Types::DECIMAL, precision: 12, scale: 2)]
    private string $tarifUnitaire = '0.00';

    #[ORM\Column(type: Types::DECIMAL, precision: 12, scale: 2)]
    private string $tarifBrut = '0.00';

    #[ORM\Column(length: 16)]
    private string $remiseType = Facture::REMISE_NONE;

    #[ORM\Column(type: Types::DECIMAL, precision: 12, scale: 2)]
    private string $remiseValeur = '0.00';

    #[ORM\Column(type: Types::DECIMAL, precision: 12, scale: 2)]
    private string $remiseMontant = '0.00';

    #[ORM\Column(type: Types::DECIMAL, precision: 12, scale: 2)]
    private string $tarifTotal = '0.00';

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getFacture(): ?Facture
    {
        return $this->facture;
    }

    public function setFacture(?Facture $facture): static
    {
        $this->facture = $facture;

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

    public function getCodeActe(): string
    {
        return $this->codeActe;
    }

    public function setCodeActe(string $codeActe): static
    {
        $this->codeActe = $codeActe;

        return $this;
    }

    public function getLibelle(): string
    {
        return $this->libelle;
    }

    public function setLibelle(string $libelle): static
    {
        $this->libelle = $libelle;

        return $this;
    }

    public function getServiceGrille(): ?string
    {
        return $this->serviceGrille;
    }

    public function setServiceGrille(?string $serviceGrille): static
    {
        $this->serviceGrille = $serviceGrille;

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

    public function getTarifUnitaire(): string
    {
        return $this->tarifUnitaire;
    }

    public function setTarifUnitaire(string $tarifUnitaire): static
    {
        $this->tarifUnitaire = $tarifUnitaire;

        return $this;
    }

    public function getTarifBrut(): string
    {
        return $this->tarifBrut;
    }

    public function setTarifBrut(string $tarifBrut): static
    {
        $this->tarifBrut = $tarifBrut;

        return $this;
    }

    public function getRemiseType(): string
    {
        return $this->remiseType;
    }

    public function setRemiseType(string $remiseType): static
    {
        $this->remiseType = $remiseType;

        return $this;
    }

    public function getRemiseValeur(): string
    {
        return $this->remiseValeur;
    }

    public function setRemiseValeur(string $remiseValeur): static
    {
        $this->remiseValeur = $remiseValeur;

        return $this;
    }

    public function getRemiseMontant(): string
    {
        return $this->remiseMontant;
    }

    public function setRemiseMontant(string $remiseMontant): static
    {
        $this->remiseMontant = $remiseMontant;

        return $this;
    }

    public function getTarifTotal(): string
    {
        return $this->tarifTotal;
    }

    public function setTarifTotal(string $tarifTotal): static
    {
        $this->tarifTotal = $tarifTotal;

        return $this;
    }
}
