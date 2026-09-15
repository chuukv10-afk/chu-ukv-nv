<?php

namespace App\Entity;

use App\Repository\ActeFinancierRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ActeFinancierRepository::class)]
#[ORM\UniqueConstraint(name: 'UNIQ_ACTE_FIN_SERVICE_LIB', columns: ['service_grille', 'libelle'])]
class ActeFinancier
{
    public const CODE_CONSULTATION = 'CONSULTATION';

    public const STATUT_ACTIF = 'ACTIF';
    public const STATUT_INACTIF = 'INACTIF';

    public const UNITE_FC = 'FC';

    public const LIBELLE_CONSULTATION_JOUR = 'Consultation médicale Jour';
    public const LIBELLE_CONSULTATION_NUIT = 'Consultation médicale Nuit';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 40, unique: true)]
    private ?string $code = null;

    #[ORM\Column(length: 180)]
    private ?string $libelle = null;

    #[ORM\Column(length: 40, nullable: true)]
    private ?string $serviceGrille = null;

    #[ORM\Column(length: 80, nullable: true)]
    private ?string $sousCategorie = null;

    /** Tarif Cat A (CDF) — colonne saisie de la grille. */
    #[ORM\Column(type: Types::DECIMAL, precision: 12, scale: 2)]
    private ?string $tarif = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 12, scale: 2)]
    private ?string $tarifA0 = '0.00';

    #[ORM\Column(type: Types::DECIMAL, precision: 12, scale: 2)]
    private ?string $tarifA1 = '0.00';

    #[ORM\Column(type: Types::DECIMAL, precision: 12, scale: 2)]
    private ?string $tarifB = '0.00';

    #[ORM\Column(type: Types::DECIMAL, precision: 12, scale: 2)]
    private ?string $tarifC = '0.00';

    #[ORM\Column(length: 15)]
    private ?string $unite = null;

    #[ORM\Column(length: 20)]
    private ?string $statut = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCode(): ?string
    {
        return $this->code;
    }

    public function setCode(string $code): static
    {
        $this->code = $code;

        return $this;
    }

    public function getLibelle(): ?string
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

    public function getSousCategorie(): ?string
    {
        return $this->sousCategorie;
    }

    public function setSousCategorie(?string $sousCategorie): static
    {
        $this->sousCategorie = $sousCategorie;

        return $this;
    }

    public function getTarif(): ?string
    {
        return $this->tarif;
    }

    public function setTarif(string $tarif): static
    {
        $this->tarif = $tarif;

        return $this;
    }

    public function getTarifA0(): ?string
    {
        return $this->tarifA0;
    }

    public function setTarifA0(string $tarifA0): static
    {
        $this->tarifA0 = $tarifA0;

        return $this;
    }

    public function getTarifA1(): ?string
    {
        return $this->tarifA1;
    }

    public function setTarifA1(string $tarifA1): static
    {
        $this->tarifA1 = $tarifA1;

        return $this;
    }

    public function getTarifB(): ?string
    {
        return $this->tarifB;
    }

    public function setTarifB(string $tarifB): static
    {
        $this->tarifB = $tarifB;

        return $this;
    }

    public function getTarifC(): ?string
    {
        return $this->tarifC;
    }

    public function setTarifC(string $tarifC): static
    {
        $this->tarifC = $tarifC;

        return $this;
    }

    public function getUnite(): ?string
    {
        return $this->unite;
    }

    public function setUnite(string $unite): static
    {
        $this->unite = $unite;

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

    public function tarifPour(?string $categorie): string
    {
        $code = CategorieTarifaire::isValid($categorie)
            ? CategorieTarifaire::normalize((string) $categorie)
            : CategorieTarifaire::A;

        return match ($code) {
            CategorieTarifaire::A0 => (string) ($this->tarifA0 ?? '0.00'),
            CategorieTarifaire::A1 => (string) ($this->tarifA1 ?? '0.00'),
            CategorieTarifaire::B => (string) ($this->tarifB ?? '0.00'),
            CategorieTarifaire::C => (string) ($this->tarifC ?? '0.00'),
            default => (string) ($this->tarif ?? '0.00'),
        };
    }
}
