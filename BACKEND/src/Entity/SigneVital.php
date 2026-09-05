<?php

namespace App\Entity;

use App\Repository\SigneVitalRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: SigneVitalRepository::class)]
class SigneVital
{
    public const STATUT_ACTIF = 'ACTIF';
    public const STATUT_INACTIF = 'INACTIF';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 15, unique: true)]
    private ?string $code = null;

    #[ORM\Column(length: 100)]
    private ?string $libelle = null;

    #[ORM\Column(length: 20, nullable: true)]
    private ?string $unite = null;

    #[ORM\Column(options: ['default' => false])]
    private bool $demandeAuTriage = false;

    #[ORM\Column(options: ['default' => false])]
    private bool $obligatoireAuTriage = false;

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

    public function getUnite(): ?string
    {
        return $this->unite;
    }

    public function setUnite(?string $unite): static
    {
        $this->unite = $unite;

        return $this;
    }

    public function isDemandeAuTriage(): bool
    {
        return $this->demandeAuTriage;
    }

    public function setDemandeAuTriage(bool $demandeAuTriage): static
    {
        $this->demandeAuTriage = $demandeAuTriage;

        return $this;
    }

    public function isObligatoireAuTriage(): bool
    {
        return $this->obligatoireAuTriage;
    }

    public function setObligatoireAuTriage(bool $obligatoireAuTriage): static
    {
        $this->obligatoireAuTriage = $obligatoireAuTriage;

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
