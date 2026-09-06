<?php

namespace App\Entity;

use App\Repository\LotRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: LotRepository::class)]
#[ORM\UniqueConstraint(name: 'UNIQ_LOT_MEDICAMENT_NUMERO', columns: ['medicament_id', 'numero_lot'])]
class Lot
{
    public const STATUT_DISPONIBLE = 'DISPONIBLE';
    public const STATUT_EPUISE = 'EPUISE';
    public const STATUT_PERIME = 'PERIME';
    public const STATUT_BLOQUE = 'BLOQUE';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?Medicament $medicament = null;

    #[ORM\Column(length: 40)]
    private ?string $numeroLot = null;

    #[ORM\Column(type: Types::DATE_IMMUTABLE)]
    private ?\DateTimeImmutable $datePeremption = null;

    #[ORM\Column]
    private int $quantiteRestante = 0;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 4)]
    private ?string $prixAchatUnitaire = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?ReceptionLigne $receptionLigne = null;

    #[ORM\Column(length: 20)]
    private ?string $statut = self::STATUT_DISPONIBLE;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getMedicament(): ?Medicament
    {
        return $this->medicament;
    }

    public function setMedicament(?Medicament $medicament): static
    {
        $this->medicament = $medicament;

        return $this;
    }

    public function getNumeroLot(): ?string
    {
        return $this->numeroLot;
    }

    public function setNumeroLot(string $numeroLot): static
    {
        $this->numeroLot = $numeroLot;

        return $this;
    }

    public function getDatePeremption(): ?\DateTimeImmutable
    {
        return $this->datePeremption;
    }

    public function setDatePeremption(\DateTimeImmutable $datePeremption): static
    {
        $this->datePeremption = $datePeremption;

        return $this;
    }

    public function getQuantiteRestante(): int
    {
        return $this->quantiteRestante;
    }

    public function setQuantiteRestante(int $quantiteRestante): static
    {
        $this->quantiteRestante = $quantiteRestante;

        return $this;
    }

    public function getPrixAchatUnitaire(): ?string
    {
        return $this->prixAchatUnitaire;
    }

    public function setPrixAchatUnitaire(string $prixAchatUnitaire): static
    {
        $this->prixAchatUnitaire = $prixAchatUnitaire;

        return $this;
    }

    public function getReceptionLigne(): ?ReceptionLigne
    {
        return $this->receptionLigne;
    }

    public function setReceptionLigne(?ReceptionLigne $receptionLigne): static
    {
        $this->receptionLigne = $receptionLigne;

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
}
