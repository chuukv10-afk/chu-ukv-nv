<?php

namespace App\Entity;

use App\Repository\VisiteMesureRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: VisiteMesureRepository::class)]
class VisiteMesure
{
    public const SOURCE_TRIAGE = 'TRIAGE';
    public const SOURCE_CONSULTATION = 'CONSULTATION';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 50)]
    private ?string $valeur = null;

    #[ORM\Column(length: 20)]
    private ?string $source = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $measuredAt = null;

    #[ORM\ManyToOne(inversedBy: 'mesures')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Visite $visite = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?SigneVital $signeVital = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Personnel $measuredBy = null;

    /**
     * @return list<string>
     */
    public static function getSources(): array
    {
        return [
            self::SOURCE_TRIAGE,
            self::SOURCE_CONSULTATION,
        ];
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getValeur(): ?string
    {
        return $this->valeur;
    }

    public function setValeur(string $valeur): static
    {
        $this->valeur = $valeur;

        return $this;
    }

    public function getSource(): ?string
    {
        return $this->source;
    }

    public function setSource(string $source): static
    {
        $this->source = $source;

        return $this;
    }

    public function getMeasuredAt(): ?\DateTimeImmutable
    {
        return $this->measuredAt;
    }

    public function setMeasuredAt(\DateTimeImmutable $measuredAt): static
    {
        $this->measuredAt = $measuredAt;

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

    public function getSigneVital(): ?SigneVital
    {
        return $this->signeVital;
    }

    public function setSigneVital(SigneVital $signeVital): static
    {
        $this->signeVital = $signeVital;

        return $this;
    }

    public function getMeasuredBy(): ?Personnel
    {
        return $this->measuredBy;
    }

    public function setMeasuredBy(?Personnel $measuredBy): static
    {
        $this->measuredBy = $measuredBy;

        return $this;
    }
}
