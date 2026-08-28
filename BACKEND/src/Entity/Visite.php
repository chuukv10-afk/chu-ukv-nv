<?php

namespace App\Entity;

use App\Repository\VisiteRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: VisiteRepository::class)]
class Visite
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 20)]
    private ?string $typeVisite = null;

    #[ORM\Column(length: 20)]
    private ?string $statut = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $enterAt = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $sortedAt = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $sortedPrevuAt = null;

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

    public function __construct()
    {
        $this->consultations = new ArrayCollection();
        $this->acteFinancierVisites = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTypeVisite(): ?string
    {
        return $this->typeVisite;
    }

    public function setTypeVisite(string $typeVisite): static
    {
        $this->typeVisite = $typeVisite;

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
            // set the owning side to null (unless already changed)
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
            // set the owning side to null (unless already changed)
            if ($acteFinancierVisite->getVisite() === $this) {
                $acteFinancierVisite->setVisite(null);
            }
        }

        return $this;
    }
}
