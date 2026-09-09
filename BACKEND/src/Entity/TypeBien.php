<?php

namespace App\Entity;

use App\Repository\TypeBienRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: TypeBienRepository::class)]
#[ORM\Table(name: 'type_bien')]
class TypeBien
{
    public const STATUT_ACTIF = 'ACTIF';
    public const STATUT_INACTIF = 'INACTIF';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 20, unique: true)]
    private ?string $code = null;

    #[ORM\Column(length: 120)]
    private ?string $libelle = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'RESTRICT')]
    private ?FamilleBien $famille = null;

    #[ORM\Column(options: ['default' => 0])]
    private int $ordre = 0;

    #[ORM\Column(length: 20)]
    private ?string $statut = self::STATUT_ACTIF;

    #[ORM\Column(options: ['default' => false])]
    private bool $seed = false;

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    /**
     * @return list<string>
     */
    public static function getStatuts(): array
    {
        return [self::STATUT_ACTIF, self::STATUT_INACTIF];
    }

    public function isSeed(): bool
    {
        return $this->seed;
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

    public function getFamille(): ?FamilleBien
    {
        return $this->famille;
    }

    public function setFamille(?FamilleBien $famille): static
    {
        $this->famille = $famille;

        return $this;
    }

    public function getOrdre(): int
    {
        return $this->ordre;
    }

    public function setOrdre(int $ordre): static
    {
        $this->ordre = $ordre;

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

    public function setSeed(bool $seed): static
    {
        $this->seed = $seed;

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
}
