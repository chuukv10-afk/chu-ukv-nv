<?php

namespace App\Entity;

use App\Entity\Contract\BlameableInterface;
use App\Entity\Trait\BlameableTrait;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'facture_reglement')]
class FactureReglement implements BlameableInterface
{
    use BlameableTrait;

    public const MODE_ESPECES = 'ESPECES';
    public const MODE_MOBILE = 'MOBILE';
    public const MODE_BANQUE = 'BANQUE';
    public const MODE_CHEQUE = 'CHEQUE';

    public const MODES = [
        self::MODE_ESPECES,
        self::MODE_MOBILE,
        self::MODE_BANQUE,
        self::MODE_CHEQUE,
    ];

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'reglements')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Facture $facture = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 12, scale: 2)]
    private string $montant = '0.00';

    #[ORM\Column(length: 20)]
    private string $mode = self::MODE_ESPECES;

    #[ORM\Column(type: Types::DATE_MUTABLE)]
    private ?\DateTimeInterface $dateReglement = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $notes = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getFacture(): ?Facture
    {
        return $this->facture;
    }

    public function setFacture(?Facture $facture): static
    {
        $this->facture = $facture;

        return $this;
    }

    public function getMontant(): string
    {
        return $this->montant;
    }

    public function setMontant(string $montant): static
    {
        $this->montant = $montant;

        return $this;
    }

    public function getMode(): string
    {
        return $this->mode;
    }

    public function setMode(string $mode): static
    {
        $this->mode = $mode;

        return $this;
    }

    public function getDateReglement(): ?\DateTimeInterface
    {
        return $this->dateReglement;
    }

    public function setDateReglement(\DateTimeInterface $dateReglement): static
    {
        $this->dateReglement = $dateReglement;

        return $this;
    }

    public function getNotes(): ?string
    {
        return $this->notes;
    }

    public function setNotes(?string $notes): static
    {
        $this->notes = $notes;

        return $this;
    }
}
