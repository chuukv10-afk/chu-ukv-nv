<?php

namespace App\Entity;

use App\Repository\HistoriqueBienRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: HistoriqueBienRepository::class)]
#[ORM\Table(name: 'historique_bien')]
class HistoriqueBien
{
    public const TYPE_CREATION = 'CREATION';
    public const TYPE_MODIFICATION = 'MODIFICATION';
    public const TYPE_MODIFICATION_CODE = 'MODIFICATION_CODE';
    public const TYPE_TRANSFERT = 'TRANSFERT';
    public const TYPE_CHANGEMENT_LOCAL = 'CHANGEMENT_LOCAL';
    public const TYPE_CHANGEMENT_ETAT = 'CHANGEMENT_ETAT';
    public const TYPE_REFORME = 'REFORME';
    public const TYPE_SUPPRESSION = 'SUPPRESSION';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?BienPatrimonial $bien = null;

    #[ORM\Column(name: 'type_evenement', length: 30)]
    private ?string $type = null;

    #[ORM\Column(length: 8, nullable: true)]
    private ?string $etatAvant = null;

    #[ORM\Column(length: 8, nullable: true)]
    private ?string $etatApres = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Service $serviceAvant = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Service $serviceApres = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?LocalIntendance $localAvant = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?LocalIntendance $localApres = null;

    #[ORM\Column(length: 40, nullable: true)]
    private ?string $codeAvant = null;

    #[ORM\Column(length: 40, nullable: true)]
    private ?string $codeApres = null;

    #[ORM\Column(length: 500, nullable: true)]
    private ?string $motif = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: 'created_by_id', nullable: true, onDelete: 'SET NULL')]
    private ?Personnel $createdBy = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getBien(): ?BienPatrimonial
    {
        return $this->bien;
    }

    public function setBien(?BienPatrimonial $bien): static
    {
        $this->bien = $bien;

        return $this;
    }

    public function getType(): ?string
    {
        return $this->type;
    }

    public function setType(string $type): static
    {
        $this->type = $type;

        return $this;
    }

    public function getEtatAvant(): ?string
    {
        return $this->etatAvant;
    }

    public function setEtatAvant(?string $etatAvant): static
    {
        $this->etatAvant = $etatAvant;

        return $this;
    }

    public function getEtatApres(): ?string
    {
        return $this->etatApres;
    }

    public function setEtatApres(?string $etatApres): static
    {
        $this->etatApres = $etatApres;

        return $this;
    }

    public function getServiceAvant(): ?Service
    {
        return $this->serviceAvant;
    }

    public function setServiceAvant(?Service $serviceAvant): static
    {
        $this->serviceAvant = $serviceAvant;

        return $this;
    }

    public function getServiceApres(): ?Service
    {
        return $this->serviceApres;
    }

    public function setServiceApres(?Service $serviceApres): static
    {
        $this->serviceApres = $serviceApres;

        return $this;
    }

    public function getLocalAvant(): ?LocalIntendance
    {
        return $this->localAvant;
    }

    public function setLocalAvant(?LocalIntendance $localAvant): static
    {
        $this->localAvant = $localAvant;

        return $this;
    }

    public function getLocalApres(): ?LocalIntendance
    {
        return $this->localApres;
    }

    public function setLocalApres(?LocalIntendance $localApres): static
    {
        $this->localApres = $localApres;

        return $this;
    }

    public function getCodeAvant(): ?string
    {
        return $this->codeAvant;
    }

    public function setCodeAvant(?string $codeAvant): static
    {
        $this->codeAvant = $codeAvant;

        return $this;
    }

    public function getCodeApres(): ?string
    {
        return $this->codeApres;
    }

    public function setCodeApres(?string $codeApres): static
    {
        $this->codeApres = $codeApres;

        return $this;
    }

    public function getMotif(): ?string
    {
        return $this->motif;
    }

    public function setMotif(?string $motif): static
    {
        $this->motif = $motif;

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
