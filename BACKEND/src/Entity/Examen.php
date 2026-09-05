<?php

namespace App\Entity;

use App\Repository\ExamenRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ExamenRepository::class)]
class Examen
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 8)]
    private ?string $code = null;

    #[ORM\Column(length: 100)]
    private ?string $libelle = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\ManyToOne(inversedBy: 'examens')]
    #[ORM\JoinColumn(nullable: false)]
    private ?TypeExamen $typeExamen = null;

    /**
     * @var Collection<int, DemandeExamen>
     */
    #[ORM\OneToMany(targetEntity: DemandeExamen::class, mappedBy: 'examen')]
    private Collection $demandeExamens;

    public function __construct()
    {
        $this->demandeExamens = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCode(): ?string
    {
        return $this->code;
    }

    public function setCode(string $code): static
    {
        $this->code = $code;

        return $this;
    }

    public function getLibelle(): ?string
    {
        return $this->libelle;
    }

    public function setLibelle(string $libelle): static
    {
        $this->libelle = $libelle;

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

    public function getTypeExamen(): ?TypeExamen
    {
        return $this->typeExamen;
    }

    public function setTypeExamen(?TypeExamen $typeExamen): static
    {
        $this->typeExamen = $typeExamen;

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
            $demandeExamen->setExamen($this);
        }

        return $this;
    }

    public function removeDemandeExamen(DemandeExamen $demandeExamen): static
    {
        if ($this->demandeExamens->removeElement($demandeExamen)) {
            if ($demandeExamen->getExamen() === $this) {
                $demandeExamen->setExamen(null);
            }
        }

        return $this;
    }
}
