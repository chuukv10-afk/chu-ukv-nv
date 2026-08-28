<?php

namespace App\Entity;

use App\Repository\DiagnosticRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: DiagnosticRepository::class)]
class Diagnostic
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 20, nullable: true)]
    private ?string $type = null;

    #[ORM\Column(length: 20)]
    private ?string $certitude = null;

    #[ORM\Column(length: 20)]
    private ?string $stadeEvolution = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $remarque = null;

    #[ORM\ManyToOne(inversedBy: 'diagnostics')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Consultation $consultation = null;

    #[ORM\ManyToOne(inversedBy: 'diagnostics')]
    private ?DemandeExamen $demandeExamen = null;

    #[ORM\ManyToOne(inversedBy: 'diagnostics')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Maladie $maladie = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getType(): ?string
    {
        return $this->type;
    }

    public function setType(?string $type): static
    {
        $this->type = $type;

        return $this;
    }

    public function getCertitude(): ?string
    {
        return $this->certitude;
    }

    public function setCertitude(string $certitude): static
    {
        $this->certitude = $certitude;

        return $this;
    }

    public function getStadeEvolution(): ?string
    {
        return $this->stadeEvolution;
    }

    public function setStadeEvolution(string $stadeEvolution): static
    {
        $this->stadeEvolution = $stadeEvolution;

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

    public function getRemarque(): ?string
    {
        return $this->remarque;
    }

    public function setRemarque(?string $remarque): static
    {
        $this->remarque = $remarque;

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

    public function getDemandeExamen(): ?DemandeExamen
    {
        return $this->demandeExamen;
    }

    public function setDemandeExamen(?DemandeExamen $demandeExamen): static
    {
        $this->demandeExamen = $demandeExamen;

        return $this;
    }

    public function getMaladie(): ?Maladie
    {
        return $this->maladie;
    }

    public function setMaladie(?Maladie $maladie): static
    {
        $this->maladie = $maladie;

        return $this;
    }
}
