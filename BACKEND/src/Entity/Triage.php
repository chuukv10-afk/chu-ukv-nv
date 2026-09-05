<?php

namespace App\Entity;

use App\Repository\TriageRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: TriageRepository::class)]
class Triage
{
    public const TYPE_RENDEZ_VOUS = 'RENDEZ_VOUS';
    public const TYPE_CONSULTATION = 'CONSULTATION';
    public const TYPE_URGENCE = 'URGENCE';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 20)]
    private ?string $typeEntree = null;

    #[ORM\Column(nullable: true)]
    private ?int $priorite = null;

    #[ORM\Column(length: 255)]
    private ?string $motif = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $triagedAt = null;

    #[ORM\OneToOne(inversedBy: 'triage')]
    #[ORM\JoinColumn(nullable: false, unique: true, onDelete: 'CASCADE')]
    private ?Visite $visite = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: 'triaged_by_id', nullable: true, onDelete: 'SET NULL')]
    private ?Personnel $triagedBy = null;

    /**
     * @var Collection<int, TriageMesure>
     */
    #[ORM\OneToMany(targetEntity: TriageMesure::class, mappedBy: 'triage', cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $mesures;

    public function __construct()
    {
        $this->mesures = new ArrayCollection();
    }

    /**
     * @return list<string>
     */
    public static function getTypesEntree(): array
    {
        return [
            self::TYPE_RENDEZ_VOUS,
            self::TYPE_CONSULTATION,
            self::TYPE_URGENCE,
        ];
    }

    public static function normalizeTypeEntree(string $type): string
    {
        return strtoupper(trim($type));
    }

    public static function isValidTypeEntree(?string $type): bool
    {
        if (null === $type || '' === trim($type)) {
            return false;
        }

        return in_array(self::normalizeTypeEntree($type), self::getTypesEntree(), true);
    }

    public static function resolveInitialVisiteStatut(string $typeEntree): string
    {
        return self::TYPE_RENDEZ_VOUS === self::normalizeTypeEntree($typeEntree)
            ? Visite::STATUT_PLANIFIEE
            : Visite::STATUT_EN_COURS;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTypeEntree(): ?string
    {
        return $this->typeEntree;
    }

    public function setTypeEntree(string $typeEntree): static
    {
        $this->typeEntree = $typeEntree;

        return $this;
    }

    public function getPriorite(): ?int
    {
        return $this->priorite;
    }

    public function setPriorite(?int $priorite): static
    {
        $this->priorite = $priorite;

        return $this;
    }

    public function getMotif(): ?string
    {
        return $this->motif;
    }

    public function setMotif(string $motif): static
    {
        $this->motif = $motif;

        return $this;
    }

    public function getTriagedAt(): ?\DateTimeImmutable
    {
        return $this->triagedAt;
    }

    public function setTriagedAt(\DateTimeImmutable $triagedAt): static
    {
        $this->triagedAt = $triagedAt;

        return $this;
    }

    public function getVisite(): ?Visite
    {
        return $this->visite;
    }

    public function setVisite(Visite $visite): static
    {
        $this->visite = $visite;

        return $this;
    }

    public function getTriagedBy(): ?Personnel
    {
        return $this->triagedBy;
    }

    public function setTriagedBy(?Personnel $triagedBy): static
    {
        $this->triagedBy = $triagedBy;

        return $this;
    }

    /**
     * @return Collection<int, TriageMesure>
     */
    public function getMesures(): Collection
    {
        return $this->mesures;
    }

    public function addMesure(TriageMesure $mesure): static
    {
        if (!$this->mesures->contains($mesure)) {
            $this->mesures->add($mesure);
            $mesure->setTriage($this);
        }

        return $this;
    }
}
