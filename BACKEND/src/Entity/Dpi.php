<?php

namespace App\Entity;

use App\Entity\Contract\BlameableInterface;
use App\Entity\Trait\BlameableTrait;
use App\Repository\DpiRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: DpiRepository::class)]
class Dpi implements BlameableInterface
{
    use BlameableTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 50)]
    private ?string $numDossier = null;

    #[ORM\Column(length: 20)]
    private ?string $statut = null;

    #[ORM\OneToOne(inversedBy: 'dpi')]
    #[ORM\JoinColumn(nullable: false, unique: true, onDelete: 'CASCADE')]
    private ?Patient $patient = null;

    /**
     * @var Collection<int, Antecedent>
     */
    #[ORM\OneToMany(targetEntity: Antecedent::class, mappedBy: 'dpi')]
    private Collection $antecedents;

    /**
     * @var Collection<int, Visite>
     */
    #[ORM\OneToMany(targetEntity: Visite::class, mappedBy: 'dpi')]
    private Collection $visites;

    public function __construct()
    {
        $this->antecedents = new ArrayCollection();
        $this->visites = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getNumDossier(): ?string
    {
        return $this->numDossier;
    }

    public function setNumDossier(string $numDossier): static
    {
        $this->numDossier = $numDossier;

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

    public function getPatient(): ?Patient
    {
        return $this->patient;
    }

    public function setPatient(?Patient $patient): static
    {
        $this->patient = $patient;

        return $this;
    }

    /**
     * @return Collection<int, Antecedent>
     */
    public function getAntecedents(): Collection
    {
        return $this->antecedents;
    }

    public function addAntecedent(Antecedent $antecedent): static
    {
        if (!$this->antecedents->contains($antecedent)) {
            $this->antecedents->add($antecedent);
            $antecedent->setDpi($this);
        }

        return $this;
    }

    public function removeAntecedent(Antecedent $antecedent): static
    {
        if ($this->antecedents->removeElement($antecedent)) {
            // set the owning side to null (unless already changed)
            if ($antecedent->getDpi() === $this) {
                $antecedent->setDpi(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, Visite>
     */
    public function getVisites(): Collection
    {
        return $this->visites;
    }

    public function addVisite(Visite $visite): static
    {
        if (!$this->visites->contains($visite)) {
            $this->visites->add($visite);
            $visite->setDpi($this);
        }

        return $this;
    }

    public function removeVisite(Visite $visite): static
    {
        if ($this->visites->removeElement($visite)) {
            // set the owning side to null (unless already changed)
            if ($visite->getDpi() === $this) {
                $visite->setDpi(null);
            }
        }

        return $this;
    }
}
