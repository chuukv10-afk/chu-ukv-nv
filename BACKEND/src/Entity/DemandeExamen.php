<?php

namespace App\Entity;

use App\Repository\DemandeExamenRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: DemandeExamenRepository::class)]
class DemandeExamen
{
    public const STATUT_DEMANDE = 'DEMANDE';
    public const STATUT_EN_COURS = 'EN_COURS';
    public const STATUT_RESULTAT_DISPONIBLE = 'RESULTAT_DISPONIBLE';
    public const STATUT_VALIDE = 'VALIDE';
    public const STATUT_ANNULEE = 'ANNULEE';
    public const STATUT_REFUSEE = 'REFUSEE';

    public const RESULTAT_MAX_LENGTH = 1500;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $demandeAt = null;

    #[ORM\Column(length: 20)]
    private ?string $statut = null;

    #[ORM\Column(length: 1500, nullable: true)]
    private ?string $resultat = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $fichier = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $noteMedecin = null;

    #[ORM\ManyToOne(inversedBy: 'demandeExamens')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Examen $examen = null;

    #[ORM\ManyToOne(inversedBy: 'demandeExamens')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Consultation $consultation = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Personnel $prescripteur = null;

    /**
     * @var Collection<int, Diagnostic>
     */
    #[ORM\OneToMany(targetEntity: Diagnostic::class, mappedBy: 'demandeExamen')]
    private Collection $diagnostics;

    public function __construct()
    {
        $this->diagnostics = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getDemandeAt(): ?\DateTimeImmutable
    {
        return $this->demandeAt;
    }

    public function setDemandeAt(\DateTimeImmutable $demandeAt): static
    {
        $this->demandeAt = $demandeAt;

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

    public function getResultat(): ?string
    {
        return $this->resultat;
    }

    public function setResultat(?string $resultat): static
    {
        $this->resultat = $resultat;

        return $this;
    }

    public function getFichier(): ?string
    {
        return $this->fichier;
    }

    public function setFichier(?string $fichier): static
    {
        $this->fichier = $fichier;

        return $this;
    }

    public function getNoteMedecin(): ?string
    {
        return $this->noteMedecin;
    }

    public function setNoteMedecin(?string $noteMedecin): static
    {
        $this->noteMedecin = $noteMedecin;

        return $this;
    }

    public function getExamen(): ?Examen
    {
        return $this->examen;
    }

    public function setExamen(?Examen $examen): static
    {
        $this->examen = $examen;

        return $this;
    }

    public function getConsultation(): ?Consultation
    {
        return $this->consultation;
    }

    public function setConsultation(?Consultation $consultation): static
    {
        $this->consultation = $consultation;

        return $this;
    }

    public function getPrescripteur(): ?Personnel
    {
        return $this->prescripteur;
    }

    public function setPrescripteur(?Personnel $prescripteur): static
    {
        $this->prescripteur = $prescripteur;

        return $this;
    }

    /**
     * @return Collection<int, Diagnostic>
     */
    public function getDiagnostics(): Collection
    {
        return $this->diagnostics;
    }

    public function addDiagnostic(Diagnostic $diagnostic): static
    {
        if (!$this->diagnostics->contains($diagnostic)) {
            $this->diagnostics->add($diagnostic);
            $diagnostic->setDemandeExamen($this);
        }

        return $this;
    }

    public function removeDiagnostic(Diagnostic $diagnostic): static
    {
        if ($this->diagnostics->removeElement($diagnostic)) {
            if ($diagnostic->getDemandeExamen() === $this) {
                $diagnostic->setDemandeExamen(null);
            }
        }

        return $this;
    }

    /** @return list<string> */
    public static function getStatuts(): array
    {
        return [
            self::STATUT_DEMANDE,
            self::STATUT_EN_COURS,
            self::STATUT_RESULTAT_DISPONIBLE,
            self::STATUT_VALIDE,
            self::STATUT_ANNULEE,
            self::STATUT_REFUSEE,
        ];
    }

    public static function normalizeStatut(?string $statut): string
    {
        return strtoupper(trim((string) $statut));
    }

    public static function isValidStatut(?string $statut): bool
    {
        return in_array(self::normalizeStatut($statut), self::getStatuts(), true);
    }

    /**
     * @return list<string>
     */
    public static function getAllowedTransitions(string $fromStatut): array
    {
        return match (self::normalizeStatut($fromStatut)) {
            self::STATUT_DEMANDE => [self::STATUT_EN_COURS, self::STATUT_ANNULEE],
            self::STATUT_EN_COURS => [self::STATUT_RESULTAT_DISPONIBLE, self::STATUT_ANNULEE, self::STATUT_REFUSEE],
            self::STATUT_RESULTAT_DISPONIBLE => [self::STATUT_VALIDE],
            default => [],
        };
    }

    public static function canTransition(string $fromStatut, string $toStatut): bool
    {
        return in_array(self::normalizeStatut($toStatut), self::getAllowedTransitions($fromStatut), true);
    }

    public static function canCancel(string $statut): bool
    {
        return in_array(self::normalizeStatut($statut), [self::STATUT_DEMANDE, self::STATUT_EN_COURS], true);
    }
}
