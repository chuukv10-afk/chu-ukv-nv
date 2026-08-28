<?php

namespace App\Entity;

use App\Repository\ConsultationRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ConsultationRepository::class)]
class Consultation
{
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

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $motif = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $histoire_maladie = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $consultation_observation = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $conduire_a_tenir = null;

    #[ORM\Column(length: 20)]
    private ?string $statut = null;

    #[ORM\ManyToOne(inversedBy: 'consultations')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Visite $visite = null;

    /**
     * @var Collection<int, Diagnostic>
     */
    #[ORM\OneToMany(targetEntity: Diagnostic::class, mappedBy: 'consultation')]
    private Collection $diagnostics;

    public function __construct()
    {
        $this->diagnostics = new ArrayCollection();
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
}
