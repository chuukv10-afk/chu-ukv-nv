<?php

namespace App\Entity;

use App\Repository\PaiePeriodeRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: PaiePeriodeRepository::class)]
#[ORM\Table(name: 'paie_periode')]
#[ORM\UniqueConstraint(name: 'UNIQ_PAIE_PERIODE_MOIS', columns: ['annee', 'mois'])]
class PaiePeriode
{
    public const STATUT_BROUILLON = 'BROUILLON';
    public const STATUT_VALIDE = 'VALIDE';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column]
    private ?int $annee = null;

    #[ORM\Column]
    private ?int $mois = null;

    #[ORM\Column(length: 20)]
    private ?string $statut = self::STATUT_BROUILLON;

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $generatedAt = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 14, scale: 2)]
    private string $totalNet = '0.00';

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Personnel $createdBy = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $valideAt = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Personnel $validePar = null;

    /**
     * @var Collection<int, PaieLigne>
     */
    #[ORM\OneToMany(targetEntity: PaieLigne::class, mappedBy: 'periode', cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $lignes;

    /**
     * @var Collection<int, PaieBaremeApplique>
     */
    #[ORM\OneToMany(targetEntity: PaieBaremeApplique::class, mappedBy: 'periode', cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $baremesAppliques;

    public function __construct()
    {
        $this->lignes = new ArrayCollection();
        $this->baremesAppliques = new ArrayCollection();
    }

    /**
     * @return list<string>
     */
    public static function getStatuts(): array
    {
        return [self::STATUT_BROUILLON, self::STATUT_VALIDE];
    }

    public function isBrouillon(): bool
    {
        return self::STATUT_BROUILLON === $this->statut;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getAnnee(): ?int
    {
        return $this->annee;
    }

    public function setAnnee(int $annee): static
    {
        $this->annee = $annee;

        return $this;
    }

    public function getMois(): ?int
    {
        return $this->mois;
    }

    public function setMois(int $mois): static
    {
        $this->mois = $mois;

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

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeImmutable $createdAt): static
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    public function getGeneratedAt(): ?\DateTimeImmutable
    {
        return $this->generatedAt;
    }

    public function setGeneratedAt(?\DateTimeImmutable $generatedAt): static
    {
        $this->generatedAt = $generatedAt;

        return $this;
    }

    public function getTotalNet(): string
    {
        return $this->totalNet;
    }

    public function setTotalNet(string $totalNet): static
    {
        $this->totalNet = $totalNet;

        return $this;
    }

    public function getCreatedBy(): ?Personnel
    {
        return $this->createdBy;
    }

    public function setCreatedBy(?Personnel $createdBy): static
    {
        $this->createdBy = $createdBy;

        return $this;
    }

    public function getValideAt(): ?\DateTimeImmutable
    {
        return $this->valideAt;
    }

    public function setValideAt(?\DateTimeImmutable $valideAt): static
    {
        $this->valideAt = $valideAt;

        return $this;
    }

    public function getValidePar(): ?Personnel
    {
        return $this->validePar;
    }

    public function setValidePar(?Personnel $validePar): static
    {
        $this->validePar = $validePar;

        return $this;
    }

    /**
     * @return Collection<int, PaieLigne>
     */
    public function getLignes(): Collection
    {
        return $this->lignes;
    }

    public function addLigne(PaieLigne $ligne): static
    {
        if (!$this->lignes->contains($ligne)) {
            $this->lignes->add($ligne);
            $ligne->setPeriode($this);
        }

        return $this;
    }

    public function removeLigne(PaieLigne $ligne): static
    {
        $this->lignes->removeElement($ligne);

        return $this;
    }

    /**
     * @return Collection<int, PaieBaremeApplique>
     */
    public function getBaremesAppliques(): Collection
    {
        return $this->baremesAppliques;
    }

    public function addBaremeApplique(PaieBaremeApplique $bareme): static
    {
        if (!$this->baremesAppliques->contains($bareme)) {
            $this->baremesAppliques->add($bareme);
            $bareme->setPeriode($this);
        }

        return $this;
    }

    public function removeBaremeApplique(PaieBaremeApplique $bareme): static
    {
        $this->baremesAppliques->removeElement($bareme);

        return $this;
    }
}
