<?php

namespace App\Entity;

use App\Entity\Contract\BlameableInterface;
use App\Entity\Trait\BlameableTrait;
use App\Repository\DemandeServiceRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: DemandeServiceRepository::class)]
class DemandeService implements BlameableInterface
{
    use BlameableTrait;

    public const STATUT_BROUILLON = 'BROUILLON';
    public const STATUT_ENVOYEE = 'ENVOYEE';
    public const STATUT_DELIVREE = 'DELIVREE';
    public const STATUT_REFUSEE = 'REFUSEE';

    public const PAIEMENT_SANS_OBJET = 'SANS_OBJET';
    public const PAIEMENT_IMPAYEE = 'IMPAYEE';
    public const PAIEMENT_PAYEE = 'PAYEE';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 30, unique: true)]
    private ?string $numero = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?Service $service = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Visite $visite = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $motif = null;

    #[ORM\Column(length: 20)]
    private ?string $statut = self::STATUT_BROUILLON;

    #[ORM\Column(length: 20)]
    private ?string $statutPaiement = self::PAIEMENT_SANS_OBJET;

    #[ORM\Column(type: Types::DECIMAL, precision: 12, scale: 4)]
    private string $montantTotal = '0.0000';

    #[ORM\Column(length: 20, nullable: true)]
    private ?string $modePaiement = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $delivreeAt = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $payeAt = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Personnel $payePar = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $motifRefus = null;

    /**
     * @var Collection<int, DemandeServiceLigne>
     */
    #[ORM\OneToMany(targetEntity: DemandeServiceLigne::class, mappedBy: 'demande', cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $lignes;

    public function __construct()
    {
        $this->lignes = new ArrayCollection();
    }

    public function getId(): ?int { return $this->id; }
    public function getNumero(): ?string { return $this->numero; }
    public function setNumero(string $numero): static { $this->numero = $numero; return $this; }
    public function getService(): ?Service { return $this->service; }
    public function setService(?Service $service): static { $this->service = $service; return $this; }
    public function getVisite(): ?Visite { return $this->visite; }
    public function setVisite(?Visite $visite): static { $this->visite = $visite; return $this; }
    public function getDelivreeAt(): ?\DateTimeImmutable { return $this->delivreeAt; }
    public function setDelivreeAt(?\DateTimeImmutable $delivreeAt): static { $this->delivreeAt = $delivreeAt; return $this; }
    public function getMotif(): ?string { return $this->motif; }
    public function setMotif(?string $motif): static { $this->motif = $motif; return $this; }
    public function getStatut(): ?string { return $this->statut; }
    public function setStatut(string $statut): static { $this->statut = $statut; return $this; }
    public function getStatutPaiement(): ?string { return $this->statutPaiement; }
    public function setStatutPaiement(string $statutPaiement): static { $this->statutPaiement = $statutPaiement; return $this; }
    public function getMontantTotal(): string { return $this->montantTotal; }
    public function setMontantTotal(string $montantTotal): static { $this->montantTotal = $montantTotal; return $this; }
    public function getModePaiement(): ?string { return $this->modePaiement; }
    public function setModePaiement(?string $modePaiement): static { $this->modePaiement = $modePaiement; return $this; }
    public function getPayeAt(): ?\DateTimeImmutable { return $this->payeAt; }
    public function setPayeAt(?\DateTimeImmutable $payeAt): static { $this->payeAt = $payeAt; return $this; }
    public function getPayePar(): ?Personnel { return $this->payePar; }
    public function setPayePar(?Personnel $payePar): static { $this->payePar = $payePar; return $this; }
    public function getMotifRefus(): ?string { return $this->motifRefus; }
    public function setMotifRefus(?string $motifRefus): static { $this->motifRefus = $motifRefus; return $this; }

    /** @return Collection<int, DemandeServiceLigne> */
    public function getLignes(): Collection { return $this->lignes; }

    public function addLigne(DemandeServiceLigne $ligne): static
    {
        if (!$this->lignes->contains($ligne)) {
            $this->lignes->add($ligne);
            $ligne->setDemande($this);
        }

        return $this;
    }

    public function clearLignes(): void
    {
        $this->lignes->clear();
    }
}
