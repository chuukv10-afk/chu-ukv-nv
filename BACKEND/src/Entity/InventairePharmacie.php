<?php

namespace App\Entity;

use App\Entity\Contract\BlameableInterface;
use App\Entity\Trait\BlameableTrait;
use App\Repository\InventairePharmacieRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: InventairePharmacieRepository::class)]
#[ORM\Index(name: 'IDX_INV_PHARMA_STATUT', columns: ['statut'])]
class InventairePharmacie implements BlameableInterface
{
    use BlameableTrait;

    public const STATUT_EN_COURS = 'EN_COURS';
    public const STATUT_CLOTURE = 'CLOTURE';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 30, unique: true)]
    private ?string $numero = null;

    #[ORM\Column(length: 150)]
    private ?string $libelle = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $notes = null;

    #[ORM\Column(length: 20)]
    private ?string $statut = self::STATUT_EN_COURS;

    #[ORM\Column(type: Types::DATE_IMMUTABLE)]
    private ?\DateTimeImmutable $dateDebut = null;

    #[ORM\Column(type: Types::DATE_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $dateCloture = null;

    #[ORM\Column]
    private int $lignesCount = 0;

    #[ORM\Column]
    private int $lignesComptees = 0;

    #[ORM\Column]
    private int $produitsCount = 0;

    #[ORM\Column]
    private int $produitsComptes = 0;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: 'cloture_par_id', nullable: true, onDelete: 'SET NULL')]
    private ?Personnel $cloturePar = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $clotureAt = null;

    /**
     * @var Collection<int, InventairePharmacieLigne>
     */
    #[ORM\OneToMany(targetEntity: InventairePharmacieLigne::class, mappedBy: 'inventaire', cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $lignes;

    public function __construct()
    {
        $this->lignes = new ArrayCollection();
    }

    /**
     * @return list<string>
     */
    public static function getStatuts(): array
    {
        return [self::STATUT_EN_COURS, self::STATUT_CLOTURE];
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getNumero(): ?string
    {
        return $this->numero;
    }

    public function setNumero(string $numero): static
    {
        $this->numero = $numero;

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

    public function getNotes(): ?string
    {
        return $this->notes;
    }

    public function setNotes(?string $notes): static
    {
        $this->notes = $notes;

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

    public function getDateDebut(): ?\DateTimeImmutable
    {
        return $this->dateDebut;
    }

    public function setDateDebut(\DateTimeImmutable $dateDebut): static
    {
        $this->dateDebut = $dateDebut;

        return $this;
    }

    public function getDateCloture(): ?\DateTimeImmutable
    {
        return $this->dateCloture;
    }

    public function setDateCloture(?\DateTimeImmutable $dateCloture): static
    {
        $this->dateCloture = $dateCloture;

        return $this;
    }

    public function getLignesCount(): int
    {
        return $this->lignesCount;
    }

    public function getLignesComptees(): int
    {
        return $this->lignesComptees;
    }

    public function getProduitsCount(): int
    {
        return $this->produitsCount;
    }

    public function getProduitsComptes(): int
    {
        return $this->produitsComptes;
    }

    public function getCloturePar(): ?Personnel
    {
        return $this->cloturePar;
    }

    public function setCloturePar(?Personnel $cloturePar): static
    {
        $this->cloturePar = $cloturePar;

        return $this;
    }

    public function getClotureAt(): ?\DateTimeImmutable
    {
        return $this->clotureAt;
    }

    public function setClotureAt(?\DateTimeImmutable $clotureAt): static
    {
        $this->clotureAt = $clotureAt;

        return $this;
    }

    /**
     * @return Collection<int, InventairePharmacieLigne>
     */
    public function getLignes(): Collection
    {
        return $this->lignes;
    }

    public function addLigne(InventairePharmacieLigne $ligne): static
    {
        if (!$this->lignes->contains($ligne)) {
            $this->lignes->add($ligne);
            $ligne->setInventaire($this);
        }

        return $this;
    }

    public function isEnCours(): bool
    {
        return self::STATUT_EN_COURS === $this->statut;
    }

    public function recomputeProgress(): void
    {
        $byMedicament = [];
        $lignesComptees = 0;

        foreach ($this->lignes as $ligne) {
            $medicamentId = $ligne->getMedicament()?->getId() ?? 0;
            if (!array_key_exists($medicamentId, $byMedicament)) {
                $byMedicament[$medicamentId] = true;
            }
            if ($ligne->isCompte()) {
                ++$lignesComptees;
            } else {
                $byMedicament[$medicamentId] = false;
            }
        }

        $this->lignesCount = $this->lignes->count();
        $this->lignesComptees = $lignesComptees;
        $this->produitsCount = count($byMedicament);
        $this->produitsComptes = count(array_filter($byMedicament));
    }
}
