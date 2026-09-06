<?php

namespace App\Entity;

use App\Entity\Contract\BlameableInterface;
use App\Entity\Trait\BlameableTrait;
use App\Repository\MouvementStockRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: MouvementStockRepository::class)]
class MouvementStock implements BlameableInterface
{
    use BlameableTrait;

    public const TYPE_ENTREE_RECEPTION = 'ENTREE_RECEPTION';
    public const TYPE_SORTIE_VENTE = 'SORTIE_VENTE';
    public const TYPE_ENTREE_ANNULATION_VENTE = 'ENTREE_ANNULATION_VENTE';
    public const TYPE_SORTIE_SERVICE = 'SORTIE_SERVICE';
    public const TYPE_SORTIE_HOSPITALISE = 'SORTIE_HOSPITALISE';
    public const TYPE_SORTIE_PERTE = 'SORTIE_PERTE';
    public const TYPE_SORTIE_PEREMPTION = 'SORTIE_PEREMPTION';
    public const TYPE_AJUSTEMENT_PLUS = 'AJUSTEMENT_PLUS';
    public const TYPE_AJUSTEMENT_MOINS = 'AJUSTEMENT_MOINS';

    public const SENS_ENTREE = 'ENTREE';
    public const SENS_SORTIE = 'SORTIE';

    public const DOC_RECEPTION = 'RECEPTION';
    public const DOC_VENTE = 'VENTE';
    public const DOC_DEMANDE_SERVICE = 'DEMANDE_SERVICE';
    public const DOC_AJUSTEMENT = 'AJUSTEMENT';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 40)]
    private ?string $type = null;

    #[ORM\Column(length: 10)]
    private ?string $sens = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?Lot $lot = null;

    #[ORM\Column]
    private int $quantite = 0;

    #[ORM\Column(length: 30)]
    private ?string $documentType = null;

    #[ORM\Column]
    private int $documentId = 0;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $motif = null;

    public function getId(): ?int
    {
        return $this->id;
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

    public function getSens(): ?string
    {
        return $this->sens;
    }

    public function setSens(string $sens): static
    {
        $this->sens = $sens;

        return $this;
    }

    public function getLot(): ?Lot
    {
        return $this->lot;
    }

    public function setLot(?Lot $lot): static
    {
        $this->lot = $lot;

        return $this;
    }

    public function getQuantite(): int
    {
        return $this->quantite;
    }

    public function setQuantite(int $quantite): static
    {
        $this->quantite = $quantite;

        return $this;
    }

    public function getDocumentType(): ?string
    {
        return $this->documentType;
    }

    public function setDocumentType(string $documentType): static
    {
        $this->documentType = $documentType;

        return $this;
    }

    public function getDocumentId(): int
    {
        return $this->documentId;
    }

    public function setDocumentId(int $documentId): static
    {
        $this->documentId = $documentId;

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
}
