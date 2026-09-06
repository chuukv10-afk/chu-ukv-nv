<?php

namespace App\Entity;

use App\Entity\Contract\BlameableInterface;
use App\Entity\Trait\BlameableTrait;
use App\Repository\VenteRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: VenteRepository::class)]
class Vente implements BlameableInterface
{
    use BlameableTrait;

    public const STATUT_BROUILLON = 'BROUILLON';
    public const STATUT_VALIDEE = 'VALIDEE';
    public const STATUT_ANNULEE = 'ANNULEE';

    public const CLIENT_PATIENT = 'PATIENT';
    public const CLIENT_PASSANT = 'PASSANT';

    public const PAIEMENT_ESPECES = 'ESPECES';
    public const PAIEMENT_MOBILE = 'MOBILE';

    public const ORIGINE_COMPTOIR = 'COMPTOIR';
    public const ORIGINE_HOSPITALISE = 'HOSPITALISE';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 30, unique: true)]
    private ?string $numero = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $dateVente = null;

    #[ORM\Column(length: 20)]
    private ?string $clientType = self::CLIENT_PASSANT;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Patient $patient = null;

    #[ORM\Column(length: 150, nullable: true)]
    private ?string $clientNom = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Visite $visite = null;

    #[ORM\Column(length: 20)]
    private ?string $origine = self::ORIGINE_COMPTOIR;

    #[ORM\Column(length: 20)]
    private ?string $modePaiement = self::PAIEMENT_ESPECES;

    #[ORM\Column(length: 20)]
    private ?string $statut = self::STATUT_BROUILLON;

    #[ORM\Column(type: Types::DECIMAL, precision: 12, scale: 4)]
    private string $montantTotal = '0.0000';

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $annuleAt = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Personnel $annulePar = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $motifAnnulation = null;

    /**
     * @var Collection<int, VenteLigne>
     */
    #[ORM\OneToMany(targetEntity: VenteLigne::class, mappedBy: 'vente', cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $lignes;

    public function __construct()
    {
        $this->lignes = new ArrayCollection();
    }

    /**
     * @return list<string>
     */
    public static function getStatuts(): array
    {
        return [self::STATUT_BROUILLON, self::STATUT_VALIDEE, self::STATUT_ANNULEE];
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getNumero(): ?string
    {
        return $this->numero;
    }

    public function setNumero(string $numero): static
    {
        $this->numero = $numero;

        return $this;
    }

    public function getDateVente(): ?\DateTimeImmutable
    {
        return $this->dateVente;
    }

    public function setDateVente(?\DateTimeImmutable $dateVente): static
    {
        $this->dateVente = $dateVente;

        return $this;
    }

    public function getClientType(): ?string
    {
        return $this->clientType;
    }

    public function setClientType(string $clientType): static
    {
        $this->clientType = $clientType;

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

    public function getClientNom(): ?string
    {
        return $this->clientNom;
    }

    public function setClientNom(?string $clientNom): static
    {
        $this->clientNom = $clientNom;

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

    public function getOrigine(): ?string
    {
        return $this->origine;
    }

    public function setOrigine(string $origine): static
    {
        $this->origine = $origine;

        return $this;
    }

    public function getModePaiement(): ?string
    {
        return $this->modePaiement;
    }

    public function setModePaiement(string $modePaiement): static
    {
        $this->modePaiement = $modePaiement;

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

    public function getMontantTotal(): string
    {
        return $this->montantTotal;
    }

    public function setMontantTotal(string $montantTotal): static
    {
        $this->montantTotal = $montantTotal;

        return $this;
    }

    public function getAnnuleAt(): ?\DateTimeImmutable
    {
        return $this->annuleAt;
    }

    public function setAnnuleAt(?\DateTimeImmutable $annuleAt): static
    {
        $this->annuleAt = $annuleAt;

        return $this;
    }

    public function getAnnulePar(): ?Personnel
    {
        return $this->annulePar;
    }

    public function setAnnulePar(?Personnel $annulePar): static
    {
        $this->annulePar = $annulePar;

        return $this;
    }

    public function getMotifAnnulation(): ?string
    {
        return $this->motifAnnulation;
    }

    public function setMotifAnnulation(?string $motifAnnulation): static
    {
        $this->motifAnnulation = $motifAnnulation;

        return $this;
    }

    /**
     * @return Collection<int, VenteLigne>
     */
    public function getLignes(): Collection
    {
        return $this->lignes;
    }

    public function addLigne(VenteLigne $ligne): static
    {
        if (!$this->lignes->contains($ligne)) {
            $this->lignes->add($ligne);
            $ligne->setVente($this);
        }

        return $this;
    }

    public function clearLignes(): void
    {
        $this->lignes->clear();
    }
}
