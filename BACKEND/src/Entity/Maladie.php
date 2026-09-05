<?php

namespace App\Entity;

use App\Repository\MaladieRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: MaladieRepository::class)]
class Maladie
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 15, unique: true)]
    private ?string $code_cim10 = null;

    #[ORM\Column(length: 255)]
    private ?string $libelle = null;

    #[ORM\Column(length: 20, nullable: true)]
    private ?string $chapitre = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    /**
     * @var Collection<int, Antecedent>
     */
    #[ORM\OneToMany(targetEntity: Antecedent::class, mappedBy: 'maladie')]
    private Collection $dpi;

    /**
     * @var Collection<int, Diagnostic>
     */
    #[ORM\OneToMany(targetEntity: Diagnostic::class, mappedBy: 'maladie')]
    private Collection $diagnostics;

    public function __construct()
    {
        $this->dpi = new ArrayCollection();
        $this->diagnostics = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCodeCim10(): ?string
    {
        return $this->code_cim10;
    }

    public function setCodeCim10(string $code_cim10): static
    {
        $this->code_cim10 = $code_cim10;

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

    public function getChapitre(): ?string
    {
        return $this->chapitre;
    }

    public function setChapitre(?string $chapitre): static
    {
        $this->chapitre = $chapitre;

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

    /**
     * @return Collection<int, Antecedent>
     */
    public function getDpi(): Collection
    {
        return $this->dpi;
    }

    public function addDpi(Antecedent $dpi): static
    {
        if (!$this->dpi->contains($dpi)) {
            $this->dpi->add($dpi);
            $dpi->setMaladie($this);
        }

        return $this;
    }

    public function removeDpi(Antecedent $dpi): static
    {
        if ($this->dpi->removeElement($dpi)) {
            // set the owning side to null (unless already changed)
            if ($dpi->getMaladie() === $this) {
                $dpi->setMaladie(null);
            }
        }

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
            $diagnostic->setMaladie($this);
        }

        return $this;
    }

    public function removeDiagnostic(Diagnostic $diagnostic): static
    {
        if ($this->diagnostics->removeElement($diagnostic)) {
            // set the owning side to null (unless already changed)
            if ($diagnostic->getMaladie() === $this) {
                $diagnostic->setMaladie(null);
            }
        }

        return $this;
    }
}
