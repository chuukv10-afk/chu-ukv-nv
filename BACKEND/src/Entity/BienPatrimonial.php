<?php

namespace App\Entity;

use App\Entity\Contract\BlameableInterface;
use App\Entity\Trait\BlameableTrait;
use App\Repository\BienPatrimonialRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: BienPatrimonialRepository::class)]
#[ORM\Table(name: 'bien_patrimonial')]
#[ORM\UniqueConstraint(name: 'UNIQ_BIEN_CODE_INVENTAIRE', columns: ['code_inventaire'])]
class BienPatrimonial implements BlameableInterface
{
    use BlameableTrait;

    public const ETAT_F = 'F';
    public const ETAT_FP = 'FP';
    public const ETAT_P = 'P';
    public const ETAT_HU = 'H.U';
    public const ETAT_R = 'R';
    public const ETAT_M = 'M';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 40)]
    private ?string $codeInventaire = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'RESTRICT')]
    private ?TypeBien $type = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'RESTRICT')]
    private ?FamilleBien $famille = null;

    #[ORM\Column(name: 'precision_texte', length: 150, nullable: true)]
    private ?string $precision = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $marque = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $modele = null;

    #[ORM\Column(length: 80, nullable: true)]
    private ?string $numeroSerie = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'RESTRICT')]
    private ?Service $service = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?LocalIntendance $local = null;

    #[ORM\Column(length: 150, nullable: true)]
    private ?string $complementLocalisation = null;

    #[ORM\Column(length: 8)]
    private ?string $etat = self::ETAT_F;

    #[ORM\Column(type: Types::DATE_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $dateAcquisition = null;

    #[ORM\Column(length: 500, nullable: true)]
    private ?string $observation = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $supprimeAt = null;

    /**
     * @return list<string>
     */
    public static function getEtats(): array
    {
        return [
            self::ETAT_F,
            self::ETAT_FP,
            self::ETAT_P,
            self::ETAT_HU,
            self::ETAT_R,
            self::ETAT_M,
        ];
    }

    public function isParcActif(): bool
    {
        return null === $this->supprimeAt && self::ETAT_R !== $this->etat;
    }

    public function isSupprime(): bool
    {
        return null !== $this->supprimeAt;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCodeInventaire(): ?string
    {
        return $this->codeInventaire;
    }

    public function setCodeInventaire(string $codeInventaire): static
    {
        $this->codeInventaire = $codeInventaire;

        return $this;
    }

    public function getType(): ?TypeBien
    {
        return $this->type;
    }

    public function setType(?TypeBien $type): static
    {
        $this->type = $type;

        return $this;
    }

    public function getFamille(): ?FamilleBien
    {
        return $this->famille;
    }

    public function setFamille(?FamilleBien $famille): static
    {
        $this->famille = $famille;

        return $this;
    }

    public function getPrecision(): ?string
    {
        return $this->precision;
    }

    public function setPrecision(?string $precision): static
    {
        $this->precision = $precision;

        return $this;
    }

    public function getMarque(): ?string
    {
        return $this->marque;
    }

    public function setMarque(?string $marque): static
    {
        $this->marque = $marque;

        return $this;
    }

    public function getModele(): ?string
    {
        return $this->modele;
    }

    public function setModele(?string $modele): static
    {
        $this->modele = $modele;

        return $this;
    }

    public function getNumeroSerie(): ?string
    {
        return $this->numeroSerie;
    }

    public function setNumeroSerie(?string $numeroSerie): static
    {
        $this->numeroSerie = $numeroSerie;

        return $this;
    }

    public function getService(): ?Service
    {
        return $this->service;
    }

    public function setService(?Service $service): static
    {
        $this->service = $service;

        return $this;
    }

    public function getLocal(): ?LocalIntendance
    {
        return $this->local;
    }

    public function setLocal(?LocalIntendance $local): static
    {
        $this->local = $local;

        return $this;
    }

    public function getComplementLocalisation(): ?string
    {
        return $this->complementLocalisation;
    }

    public function setComplementLocalisation(?string $complementLocalisation): static
    {
        $this->complementLocalisation = $complementLocalisation;

        return $this;
    }

    public function getEtat(): ?string
    {
        return $this->etat;
    }

    public function setEtat(string $etat): static
    {
        $this->etat = $etat;

        return $this;
    }

    public function getDateAcquisition(): ?\DateTimeImmutable
    {
        return $this->dateAcquisition;
    }

    public function setDateAcquisition(?\DateTimeImmutable $dateAcquisition): static
    {
        $this->dateAcquisition = $dateAcquisition;

        return $this;
    }

    public function getObservation(): ?string
    {
        return $this->observation;
    }

    public function setObservation(?string $observation): static
    {
        $this->observation = $observation;

        return $this;
    }

    public function getSupprimeAt(): ?\DateTimeImmutable
    {
        return $this->supprimeAt;
    }

    public function setSupprimeAt(?\DateTimeImmutable $supprimeAt): static
    {
        $this->supprimeAt = $supprimeAt;

        return $this;
    }
}
