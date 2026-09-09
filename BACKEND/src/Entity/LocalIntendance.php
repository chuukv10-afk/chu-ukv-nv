<?php

namespace App\Entity;

use App\Repository\LocalIntendanceRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: LocalIntendanceRepository::class)]
#[ORM\Table(name: 'local_intendance')]
#[ORM\UniqueConstraint(name: 'UNIQ_LOCAL_INTENDANCE_SERVICE_CODE', columns: ['service_id', 'code'])]
class LocalIntendance
{
    public const STATUT_ACTIF = 'ACTIF';
    public const STATUT_INACTIF = 'INACTIF';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'RESTRICT')]
    private ?Service $service = null;

    #[ORM\Column(length: 20)]
    private ?string $code = null;

    #[ORM\Column(length: 120)]
    private ?string $libelle = null;

    #[ORM\Column(options: ['default' => 0])]
    private int $ordre = 0;

    #[ORM\Column(length: 20)]
    private ?string $statut = self::STATUT_ACTIF;

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    /**
     * @return list<string>
     */
    public static function getStatuts(): array
    {
        return [self::STATUT_ACTIF, self::STATUT_INACTIF];
    }

    public function getId(): ?int
    {
        return $this->id;
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
