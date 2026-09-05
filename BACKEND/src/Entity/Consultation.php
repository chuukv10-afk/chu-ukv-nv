<?php

namespace App\Entity;

use App\Repository\ConsultationRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ConsultationRepository::class)]
class Consultation
{
    public const STATUT_PLANIFIEE = 'PLANIFIEE';
    public const STATUT_EN_COURS = 'EN_COURS';
    public const STATUT_TERMINEE = 'TERMINEE';
    public const STATUT_ANNULEE = 'ANNULEE';

    public const TYPE_NORMALE = 'NORMALE';
    public const TYPE_AU_LIT = 'AU_LIT';
    public const TYPE_URGENCE = 'URGENCE';
    public const TYPE_INITIALE = 'INITIALE';
    public const TYPE_SUIVI = 'SUIVI';
    public const TYPE_SPECIALISTE = 'SPECIALISTE';

    public const OPINION_FAVORABLE = 'FAVORABLE';
    public const OPINION_NON_FAVORABLE = 'NON_FAVORABLE';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $consultedAt = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $debutAt = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $finAt = null;

    #[ORM\Column(length: 20, nullable: true)]
    private ?string $typeConsultation = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $motif = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $histoire_maladie = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $consultation_observation = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $conduire_a_tenir = null;

    /** @var array<string, mixed>|null */
    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $physicalExam = null;

    /** @var list<string>|null */
    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $complementAnamnese = null;

    /** @var array<string, mixed>|null */
    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $evolutionSheet = null;

    #[ORM\Column(nullable: true)]
    private ?bool $needsHospitalization = null;

    #[ORM\Column(nullable: true)]
    private ?bool $wantsAppointment = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $nextAppointmentAt = null;

    #[ORM\Column(length: 20, nullable: true)]
    private ?string $hospitalizationPatientOpinion = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $hospitalizationObservation = null;

    #[ORM\Column(length: 20)]
    private ?string $statut = null;

    #[ORM\ManyToOne(inversedBy: 'consultations')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Visite $visite = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Personnel $openedBy = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Personnel $closedBy = null;

    /**
     * @var Collection<int, Diagnostic>
     */
    #[ORM\OneToMany(targetEntity: Diagnostic::class, mappedBy: 'consultation')]
    private Collection $diagnostics;

    /**
     * @var Collection<int, DemandeExamen>
     */
    #[ORM\OneToMany(targetEntity: DemandeExamen::class, mappedBy: 'consultation')]
    private Collection $demandeExamens;

    public function __construct()
    {
        $this->diagnostics = new ArrayCollection();
        $this->demandeExamens = new ArrayCollection();
    }

    public static function isValidHospitalizationOpinion(?string $opinion): bool
    {
        if (null === $opinion || '' === trim($opinion)) {
            return false;
        }

        return in_array(strtoupper(trim($opinion)), [self::OPINION_FAVORABLE, self::OPINION_NON_FAVORABLE], true);
    }

    public function isClosed(): bool
    {
        return in_array($this->statut, [self::STATUT_TERMINEE, self::STATUT_ANNULEE], true);
    }

    public function isEditable(): bool
    {
        return self::isEditableStatut($this->statut);
    }

    /**
     * @return list<string>
     */
    public static function getHospitalizationOpinions(): array
    {
        return [self::OPINION_FAVORABLE, self::OPINION_NON_FAVORABLE];
    }

    /**
     * @return list<string>
     */
    public static function getStatuts(): array
    {
        return [
            self::STATUT_PLANIFIEE,
            self::STATUT_EN_COURS,
            self::STATUT_TERMINEE,
            self::STATUT_ANNULEE,
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

    /**
     * @return list<string>
     */
    public static function getTypes(): array
    {
        return [
            self::TYPE_NORMALE,
            self::TYPE_AU_LIT,
            self::TYPE_URGENCE,
            self::TYPE_INITIALE,
            self::TYPE_SUIVI,
            self::TYPE_SPECIALISTE,
        ];
    }

    /**
     * @return list<string>
     */
    public static function getCreatableTypes(): array
    {
        return [
            self::TYPE_NORMALE,
            self::TYPE_AU_LIT,
            self::TYPE_URGENCE,
        ];
    }

    public static function normalizeStatut(string $statut): string
    {
        $normalized = strtoupper(trim($statut));

        return 'ANNULE' === $normalized ? self::STATUT_ANNULEE : $normalized;
    }

    public static function normalizeType(?string $type): ?string
    {
        if (null === $type || '' === trim($type)) {
            return null;
        }

        return strtoupper(trim($type));
    }

    public static function isValidStatut(?string $statut): bool
    {
        if (null === $statut || '' === trim($statut)) {
            return false;
        }

        return in_array(self::normalizeStatut($statut), self::getStatuts(), true);
    }

    public static function isValidCreatableStatut(?string $statut): bool
    {
        if (null === $statut || '' === trim($statut)) {
            return false;
        }

        return in_array(self::normalizeStatut($statut), self::getCreatableStatuts(), true);
    }

    public static function isValidCreatableType(?string $type): bool
    {
        if (null === $type || '' === trim($type)) {
            return false;
        }

        return in_array(self::normalizeType($type), self::getCreatableTypes(), true);
    }

    public static function isEditableStatut(?string $statut): bool
    {
        if (null === $statut || '' === trim($statut)) {
            return false;
        }

        return in_array(self::normalizeStatut($statut), [self::STATUT_PLANIFIEE, self::STATUT_EN_COURS], true);
    }

    /**
     * @return list<string>
     */
    public static function getAllowedTransitions(string $fromStatut): array
    {
        return match (self::normalizeStatut($fromStatut)) {
            self::STATUT_PLANIFIEE => [self::STATUT_EN_COURS, self::STATUT_ANNULEE],
            self::STATUT_EN_COURS => [self::STATUT_TERMINEE, self::STATUT_ANNULEE],
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

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getConsultedAt(): ?\DateTimeImmutable
    {
        return $this->consultedAt;
    }

    public function setConsultedAt(\DateTimeImmutable $consultedAt): static
    {
        $this->consultedAt = $consultedAt;

        return $this;
    }

    public function getDebutAt(): ?\DateTimeImmutable
    {
        return $this->debutAt;
    }

    public function setDebutAt(?\DateTimeImmutable $debutAt): static
    {
        $this->debutAt = $debutAt;

        return $this;
    }

    public function getFinAt(): ?\DateTimeImmutable
    {
        return $this->finAt;
    }

    public function setFinAt(?\DateTimeImmutable $finAt): static
    {
        $this->finAt = $finAt;

        return $this;
    }

    public function getTypeConsultation(): ?string
    {
        return $this->typeConsultation;
    }

    public function setTypeConsultation(?string $typeConsultation): static
    {
        $this->typeConsultation = $typeConsultation;

        return $this;
    }

    public function getMotif(): ?string
    {
        return $this->motif;
    }

    public function setMotif(?string $motif): static
    {
        $this->motif = $motif;

        return $this;
    }

    public function getHistoireMaladie(): ?string
    {
        return $this->histoire_maladie;
    }

    public function setHistoireMaladie(?string $histoire_maladie): static
    {
        $this->histoire_maladie = $histoire_maladie;

        return $this;
    }

    public function getConsultationObservation(): ?string
    {
        return $this->consultation_observation;
    }

    public function setConsultationObservation(?string $consultation_observation): static
    {
        $this->consultation_observation = $consultation_observation;

        return $this;
    }

    public function getConduireATenir(): ?string
    {
        return $this->conduire_a_tenir;
    }

    public function setConduireATenir(?string $conduire_a_tenir): static
    {
        $this->conduire_a_tenir = $conduire_a_tenir;

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

    public function getVisite(): ?Visite
    {
        return $this->visite;
    }

    public function setVisite(?Visite $visite): static
    {
        $this->visite = $visite;

        return $this;
    }

    public function getOpenedBy(): ?Personnel
    {
        return $this->openedBy;
    }

    public function setOpenedBy(?Personnel $openedBy): static
    {
        $this->openedBy = $openedBy;

        return $this;
    }

    public function getClosedBy(): ?Personnel
    {
        return $this->closedBy;
    }

    public function setClosedBy(?Personnel $closedBy): static
    {
        $this->closedBy = $closedBy;

        return $this;
    }

    /** @return array<string, mixed>|null */
    public function getPhysicalExam(): ?array
    {
        return $this->physicalExam;
    }

    /** @param array<string, mixed>|null $physicalExam */
    public function setPhysicalExam(?array $physicalExam): static
    {
        $this->physicalExam = $physicalExam;

        return $this;
    }

    /** @return list<string>|null */
    public function getComplementAnamnese(): ?array
    {
        return $this->complementAnamnese;
    }

    /** @param list<string>|null $complementAnamnese */
    public function setComplementAnamnese(?array $complementAnamnese): static
    {
        $this->complementAnamnese = $complementAnamnese;

        return $this;
    }

    /** @return array<string, mixed>|null */
    public function getEvolutionSheet(): ?array
    {
        return $this->evolutionSheet;
    }

    /** @param array<string, mixed>|null $evolutionSheet */
    public function setEvolutionSheet(?array $evolutionSheet): static
    {
        $this->evolutionSheet = $evolutionSheet;

        return $this;
    }

    public function getPhysicalExamText(): ?string
    {
        return $this->consultation_observation;
    }

    public function setPhysicalExamText(?string $text): static
    {
        $this->consultation_observation = $text;

        return $this;
    }

    public function getNeedsHospitalization(): ?bool
    {
        return $this->needsHospitalization;
    }

    public function setNeedsHospitalization(?bool $needsHospitalization): static
    {
        $this->needsHospitalization = $needsHospitalization;

        return $this;
    }

    public function getWantsAppointment(): ?bool
    {
        return $this->wantsAppointment;
    }

    public function setWantsAppointment(?bool $wantsAppointment): static
    {
        $this->wantsAppointment = $wantsAppointment;

        return $this;
    }

    public function getNextAppointmentAt(): ?\DateTimeImmutable
    {
        return $this->nextAppointmentAt;
    }

    public function setNextAppointmentAt(?\DateTimeImmutable $nextAppointmentAt): static
    {
        $this->nextAppointmentAt = $nextAppointmentAt;

        return $this;
    }

    public function getHospitalizationPatientOpinion(): ?string
    {
        return $this->hospitalizationPatientOpinion;
    }

    public function setHospitalizationPatientOpinion(?string $hospitalizationPatientOpinion): static
    {
        $this->hospitalizationPatientOpinion = $hospitalizationPatientOpinion;

        return $this;
    }

    public function getHospitalizationObservation(): ?string
    {
        return $this->hospitalizationObservation;
    }

    public function setHospitalizationObservation(?string $hospitalizationObservation): static
    {
        $this->hospitalizationObservation = $hospitalizationObservation;

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
            $diagnostic->setConsultation($this);
        }

        return $this;
    }

    public function removeDiagnostic(Diagnostic $diagnostic): static
    {
        if ($this->diagnostics->removeElement($diagnostic)) {
            // set the owning side to null (unless already changed)
            if ($diagnostic->getConsultation() === $this) {
                $diagnostic->setConsultation(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, DemandeExamen>
     */
    public function getDemandeExamens(): Collection
    {
        return $this->demandeExamens;
    }

    public function addDemandeExamen(DemandeExamen $demandeExamen): static
    {
        if (!$this->demandeExamens->contains($demandeExamen)) {
            $this->demandeExamens->add($demandeExamen);
            $demandeExamen->setConsultation($this);
        }

        return $this;
    }

    public function removeDemandeExamen(DemandeExamen $demandeExamen): static
    {
        if ($this->demandeExamens->removeElement($demandeExamen)) {
            if ($demandeExamen->getConsultation() === $this) {
                $demandeExamen->setConsultation(null);
            }
        }

        return $this;
    }
}
