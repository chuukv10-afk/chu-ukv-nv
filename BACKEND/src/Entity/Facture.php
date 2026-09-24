<?php

namespace App\Entity;

use App\Entity\Contract\BlameableInterface;
use App\Entity\Trait\BlameableTrait;
use App\Repository\FactureRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: FactureRepository::class)]
#[ORM\Table(name: 'facture')]
#[ORM\UniqueConstraint(name: 'UNIQ_FACTURE_NUMERO', columns: ['numero'])]
#[ORM\Index(name: 'IDX_FACTURE_STATUT', columns: ['statut'])]
#[ORM\Index(name: 'IDX_FACTURE_DATE', columns: ['date_facture'])]
class Facture implements BlameableInterface
{
    use BlameableTrait;

    public const STATUT_BROUILLON = 'BROUILLON';
    public const STATUT_VALIDEE = 'VALIDEE';
    public const STATUT_ANNULEE = 'ANNULEE';

    public const STATUTS = [
        self::STATUT_BROUILLON,
        self::STATUT_VALIDEE,
        self::STATUT_ANNULEE,
    ];

    public const REMISE_NONE = 'NONE';
    public const REMISE_POURCENTAGE = 'POURCENTAGE';
    public const REMISE_MONTANT = 'MONTANT';

    public const REMISE_TYPES = [
        self::REMISE_NONE,
        self::REMISE_POURCENTAGE,
        self::REMISE_MONTANT,
    ];

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 40)]
    private string $numero = '';

    #[ORM\Column(type: Types::DATE_MUTABLE)]
    private ?\DateTimeInterface $dateFacture = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'RESTRICT')]
    private ?Patient $patient = null;

    #[ORM\Column(length: 8)]
    private string $categorieTarifaire = CategorieTarifaire::A;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Structure $structure = null;

    #[ORM\Column(length: 80, nullable: true)]
    private ?string $numeroAffiliation = null;

    #[ORM\Column(length: 16)]
    private string $statut = self::STATUT_BROUILLON;

    #[ORM\Column(type: Types::DECIMAL, precision: 12, scale: 2)]
    private string $montantBrut = '0.00';

    #[ORM\Column(length: 16)]
    private string $remiseType = self::REMISE_NONE;

    #[ORM\Column(type: Types::DECIMAL, precision: 12, scale: 2)]
    private string $remiseValeur = '0.00';

    #[ORM\Column(type: Types::DECIMAL, precision: 12, scale: 2)]
    private string $remiseMontant = '0.00';

    #[ORM\Column(type: Types::DECIMAL, precision: 12, scale: 2)]
    private string $montantTotal = '0.00';

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $notes = null;

    /** @var Collection<int, FactureLigne> */
    #[ORM\OneToMany(targetEntity: FactureLigne::class, mappedBy: 'facture', cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[ORM\OrderBy(['id' => 'ASC'])]
    private Collection $lignes;

    public function __construct()
    {
        $this->lignes = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getNumero(): string
    {
        return $this->numero;
    }

    public function setNumero(string $numero): static
    {
        $this->numero = $numero;

        return $this;
    }

    public function getDateFacture(): ?\DateTimeInterface
    {
        return $this->dateFacture;
    }

    public function setDateFacture(\DateTimeInterface $dateFacture): static
    {
        $this->dateFacture = $dateFacture;

        return $this;
    }

    public function getPatient(): ?Patient
    {
        return $this->patient;
    }

    public function setPatient(?Patient $patient): static
    {
        $this->patient = $patient;

        return $this;
    }

    public function getCategorieTarifaire(): string
    {
        return $this->categorieTarifaire;
    }

    public function setCategorieTarifaire(string $categorieTarifaire): static
    {
        $this->categorieTarifaire = $categorieTarifaire;

        return $this;
    }

    public function getStructure(): ?Structure
    {
        return $this->structure;
    }

    public function setStructure(?Structure $structure): static
    {
        $this->structure = $structure;

        return $this;
    }

    public function getNumeroAffiliation(): ?string
    {
        return $this->numeroAffiliation;
    }

    public function setNumeroAffiliation(?string $numeroAffiliation): static
    {
        $this->numeroAffiliation = $numeroAffiliation;

        return $this;
    }

    public function getStatut(): string
    {
        return $this->statut;
    }

    public function setStatut(string $statut): static
    {
        $this->statut = $statut;

        return $this;
    }

    public function getMontantBrut(): string
    {
        return $this->montantBrut;
    }

    public function setMontantBrut(string $montantBrut): static
    {
        $this->montantBrut = $montantBrut;

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

    public function getMontantTotal(): string
    {
        return $this->montantTotal;
    }

    public function setMontantTotal(string $montantTotal): static
    {
        $this->montantTotal = $montantTotal;

        return $this;
    }

    public function getNotes(): ?string
    {
        return $this->notes;
    }

    public function setNotes(?string $notes): static
    {
        $this->notes = $notes;

        return $this;
    }

    /**
     * @return Collection<int, FactureLigne>
     */
    public function getLignes(): Collection
    {
        return $this->lignes;
    }

    public function addLigne(FactureLigne $ligne): static
    {
        if (!$this->lignes->contains($ligne)) {
            $this->lignes->add($ligne);
            $ligne->setFacture($this);
        }

        return $this;
    }

    public function clearLignes(): static
    {
        foreach ($this->lignes as $ligne) {
            $this->lignes->removeElement($ligne);
        }

        return $this;
    }

    public function isBrouillon(): bool
    {
        return self::STATUT_BROUILLON === $this->statut;
    }

    public function isValidee(): bool
    {
        return self::STATUT_VALIDEE === $this->statut;
    }

    public function getPatientId(): ?Uuid
    {
        return $this->patient?->getId();
    }
}
