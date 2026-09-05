<?php

namespace App\Entity;

use App\Repository\VisiteRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: VisiteRepository::class)]
class Visite
{
    public const STATUT_PLANIFIEE = 'PLANIFIEE';
    public const STATUT_EN_COURS = 'EN_COURS';
    public const STATUT_HOSPITALISE = 'HOSPITALISE';
    public const STATUT_TERMINEE = 'TERMINEE';
    public const STATUT_ANNULEE = 'ANNULEE';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 20)]
    private ?string $statut = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $enterAt = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $sortedAt = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $sortedPrevuAt = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $hospitalizedAt = null;

    #[ORM\ManyToOne(inversedBy: 'visites')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Dpi $dpi = null;

    /**
     * @var Collection<int, Consultation>
     */
    #[ORM\OneToMany(targetEntity: Consultation::class, mappedBy: 'visite')]
    private Collection $consultations;

    #[ORM\ManyToOne(inversedBy: 'visites')]
    private ?Lit $lit = null;

    #[ORM\ManyToOne(inversedBy: 'visites')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Service $service = null;

    /**
     * @var Collection<int, ActeFinancierVisite>
     */
    #[ORM\OneToMany(targetEntity: ActeFinancierVisite::class, mappedBy: 'visite')]
    private Collection $acteFinancierVisites;

    #[ORM\OneToOne(mappedBy: 'visite', cascade: ['persist', 'remove'])]
    private ?Triage $triage = null;

    /**
     * @var Collection<int, VisiteMesure>
     */
    #[ORM\OneToMany(targetEntity: VisiteMesure::class, mappedBy: 'visite', cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $mesures;

    public function __construct()
    {
        $this->consultations = new ArrayCollection();
        $this->acteFinancierVisites = new ArrayCollection();
        $this->mesures = new ArrayCollection();
    }

    /**
     * @return list<string>
     */
    public static function getStatuts(): array
    {
        return [
            self::STATUT_PLANIFIEE,
            self::STATUT_EN_COURS,
            self::STATUT_HOSPITALISE,
            self::STATUT_TERMINEE,
            self::STATUT_ANNULEE,
        ];
    }

    /**
     * @return list<string>
     */
    public static function getActiveStatuts(): array
    {
        return [
            self::STATUT_PLANIFIEE,
            self::STATUT_EN_COURS,
            self::STATUT_HOSPITALISE,
        ];
    }

    /**
     * @return list<string>
     */
    public static function getCreatableStatuts(): array
    {
        return [
            self::STATUT_PLANIFIEE,
            self::STATUT_EN_COURS,
        ];
    }

    public static function normalizeStatut(string $statut): string
    {
        return strtoupper(trim($statut));
    }

    public static function isValidStatut(?string $statut): bool
    {
        if (null === $statut || '' === trim($statut)) {
            return false;
        }

        return in_array(self::normalizeStatut($statut), self::getStatuts(), true);
    }

    public static function isActiveStatut(?string $statut): bool
    {
        if (null === $statut || '' === trim($statut)) {
            return false;
        }

        return in_array(self::normalizeStatut($statut), self::getActiveStatuts(), true);
    }

    /**
     * @return list<string>
     */
    public static function getAllowedTransitions(string $fromStatut): array
    {
        return match (self::normalizeStatut($fromStatut)) {
            self::STATUT_PLANIFIEE => [self::STATUT_EN_COURS, self::STATUT_ANNULEE],
            self::STATUT_EN_COURS => [self::STATUT_HOSPITALISE, self::STATUT_TERMINEE, self::STATUT_ANNULEE],
            self::STATUT_HOSPITALISE => [self::STATUT_TERMINEE, self::STATUT_ANNULEE],
            default => [],
        };
    }

    public static function canTransition(string $fromStatut, string $toStatut): bool
    {
        $from = self::normalizeStatut($fromStatut);
        $to = self::normalizeStatut($toStatut);

        if ($from === $to) {
            return true;
        }

        return in_array($to, self::getAllowedTransitions($from), true);
    }

    public function isActive(): bool
    {
        return self::isActiveStatut($this->statut);
    }

    public function getId(): ?int
    {
        return $this->id;
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

    public function getEnterAt(): ?\DateTimeImmutable
    {
        return $this->enterAt;
    }

    public function setEnterAt(\DateTimeImmutable $enterAt): static
    {
        $this->enterAt = $enterAt;

        return $this;
    }

    public function getSortedAt(): ?\DateTimeImmutable
    {
        return $this->sortedAt;
    }

    public function setSortedAt(?\DateTimeImmutable $sortedAt): static
    {
        $this->sortedAt = $sortedAt;

        return $this;
    }

    public function getSortedPrevuAt(): ?\DateTimeImmutable
    {
        return $this->sortedPrevuAt;
    }

    public function setSortedPrevuAt(?\DateTimeImmutable $sortedPrevuAt): static
    {
        $this->sortedPrevuAt = $sortedPrevuAt;

        return $this;
    }

    public function getHospitalizedAt(): ?\DateTimeImmutable
    {
        return $this->hospitalizedAt;
    }

    public function setHospitalizedAt(?\DateTimeImmutable $hospitalizedAt): static
    {
        $this->hospitalizedAt = $hospitalizedAt;

        return $this;
    }

    public function isCurrentHospitalization(): bool
    {
        return self::STATUT_HOSPITALISE === $this->statut;
    }

    public function isHospitalization(): bool
    {
        return $this->isCurrentHospitalization() || null !== $this->hospitalizedAt;
    }

    public function getDpi(): ?Dpi
    {
        return $this->dpi;
    }

    public function setDpi(?Dpi $dpi): static
    {
        $this->dpi = $dpi;

        return $this;
    }

    /**
     * @return Collection<int, Consultation>
     */
    public function getConsultations(): Collection
    {
        return $this->consultations;
    }

    public function addConsultation(Consultation $consultation): static
    {
        if (!$this->consultations->contains($consultation)) {
            $this->consultations->add($consultation);
            $consultation->setVisite($this);
        }

        return $this;
    }

    public function removeConsultation(Consultation $consultation): static
    {
        if ($this->consultations->removeElement($consultation)) {
            if ($consultation->getVisite() === $this) {
                $consultation->setVisite(null);
            }
        }

        return $this;
    }

    public function getLit(): ?Lit
    {
        return $this->lit;
    }

    public function setLit(?Lit $lit): static
    {
        $this->lit = $lit;

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

    /**
     * @return Collection<int, ActeFinancierVisite>
     */
    public function getActeFinancierVisites(): Collection
    {
        return $this->acteFinancierVisites;
    }

    public function addActeFinancierVisite(ActeFinancierVisite $acteFinancierVisite): static
    {
        if (!$this->acteFinancierVisites->contains($acteFinancierVisite)) {
            $this->acteFinancierVisites->add($acteFinancierVisite);
            $acteFinancierVisite->setVisite($this);
        }

        return $this;
    }

    public function removeActeFinancierVisite(ActeFinancierVisite $acteFinancierVisite): static
    {
        if ($this->acteFinancierVisites->removeElement($acteFinancierVisite)) {
            if ($acteFinancierVisite->getVisite() === $this) {
                $acteFinancierVisite->setVisite(null);
            }
        }

        return $this;
    }

    public function getTriage(): ?Triage
    {
        return $this->triage;
    }

    public function setTriage(Triage $triage): static
    {
        if ($this->triage !== $triage) {
            $this->triage = $triage;
            $triage->setVisite($this);
        }

        return $this;
    }

    /**
     * @return Collection<int, VisiteMesure>
     */
    public function getMesures(): Collection
    {
        return $this->mesures;
    }

    public function addMesure(VisiteMesure $mesure): static
    {
        if (!$this->mesures->contains($mesure)) {
            $this->mesures->add($mesure);
            $mesure->setVisite($this);
        }

        return $this;
    }
}
