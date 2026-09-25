<?php

namespace App\Entity;

use App\Repository\EtudeImagerieRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: EtudeImagerieRepository::class)]
class EtudeImagerie
{
    public const STATUT_EN_ATTENTE = 'EN_ATTENTE';
    public const STATUT_IMAGES = 'IMAGES';
    public const STATUT_INTERPRETE = 'INTERPRETE';
    public const STATUT_VALIDE = 'VALIDE';
    public const STATUT_ANNULEE = 'ANNULEE';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 30, unique: true)]
    private ?string $numero = null;

    #[ORM\Column(length: 20)]
    private ?string $statut = self::STATUT_EN_ATTENTE;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $indication = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $but = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $technique = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $constatations = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $conclusion = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Patient $patient = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?Examen $examen = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Consultation $consultation = null;

    #[ORM\OneToOne]
    #[ORM\JoinColumn(nullable: true, unique: true, onDelete: 'SET NULL')]
    private ?DemandeExamen $demandeExamen = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Personnel $demandePar = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Personnel $createdBy = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Personnel $interpretePar = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $interpreteAt = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Personnel $validePar = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $valideAt = null;

    /**
     * @var Collection<int, ImageImagerie>
     */
    #[ORM\OneToMany(targetEntity: ImageImagerie::class, mappedBy: 'etude', cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $images;

    public function __construct()
    {
        $this->images = new ArrayCollection();
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

    public function getStatut(): ?string
    {
        return $this->statut;
    }

    public function setStatut(string $statut): static
    {
        $this->statut = $statut;

        return $this;
    }

    public function getIndication(): ?string
    {
        return $this->indication;
    }

    public function setIndication(?string $indication): static
    {
        $this->indication = $indication;

        return $this;
    }

    public function getBut(): ?string
    {
        return $this->but;
    }

    public function setBut(?string $but): static
    {
        $this->but = $but;

        return $this;
    }

    public function getTechnique(): ?string
    {
        return $this->technique;
    }

    public function setTechnique(?string $technique): static
    {
        $this->technique = $technique;

        return $this;
    }

    public function getConstatations(): ?string
    {
        return $this->constatations;
    }

    public function setConstatations(?string $constatations): static
    {
        $this->constatations = $constatations;

        return $this;
    }

    public function getConclusion(): ?string
    {
        return $this->conclusion;
    }

    public function setConclusion(?string $conclusion): static
    {
        $this->conclusion = $conclusion;

        return $this;
    }

    public function getPatient(): ?Patient
    {
        return $this->patient;
    }

    public function setPatient(?Patient $patient): static
    {
        $this->patient = $patient;

        return $this;
    }

    public function getExamen(): ?Examen
    {
        return $this->examen;
    }

    public function setExamen(?Examen $examen): static
    {
        $this->examen = $examen;

        return $this;
    }

    public function getConsultation(): ?Consultation
    {
        return $this->consultation;
    }

    public function setConsultation(?Consultation $consultation): static
    {
        $this->consultation = $consultation;

        return $this;
    }

    public function getDemandeExamen(): ?DemandeExamen
    {
        return $this->demandeExamen;
    }

    public function setDemandeExamen(?DemandeExamen $demandeExamen): static
    {
        $this->demandeExamen = $demandeExamen;

        return $this;
    }

    public function getDemandePar(): ?Personnel
    {
        return $this->demandePar;
    }

    public function setDemandePar(?Personnel $demandePar): static
    {
        $this->demandePar = $demandePar;

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

    public function getInterpretePar(): ?Personnel
    {
        return $this->interpretePar;
    }

    public function setInterpretePar(?Personnel $interpretePar): static
    {
        $this->interpretePar = $interpretePar;

        return $this;
    }

    public function getInterpreteAt(): ?\DateTimeImmutable
    {
        return $this->interpreteAt;
    }

    public function setInterpreteAt(?\DateTimeImmutable $interpreteAt): static
    {
        $this->interpreteAt = $interpreteAt;

        return $this;
    }

    public function getValidePar(): ?Personnel
    {
        return $this->validePar;
    }

    public function setValidePar(?Personnel $validePar): static
    {
        $this->validePar = $validePar;

        return $this;
    }

    public function getValideAt(): ?\DateTimeImmutable
    {
        return $this->valideAt;
    }

    public function setValideAt(?\DateTimeImmutable $valideAt): static
    {
        $this->valideAt = $valideAt;

        return $this;
    }

    /**
     * @return Collection<int, ImageImagerie>
     */
    public function getImages(): Collection
    {
        return $this->images;
    }

    public function addImage(ImageImagerie $image): static
    {
        if (!$this->images->contains($image)) {
            $this->images->add($image);
            $image->setEtude($this);
        }

        return $this;
    }

    public function removeImage(ImageImagerie $image): static
    {
        if ($this->images->removeElement($image)) {
            if ($image->getEtude() === $this) {
                $image->setEtude(null);
            }
        }

        return $this;
    }

    /** @return list<string> */
    public static function getStatuts(): array
    {
        return [
            self::STATUT_EN_ATTENTE,
            self::STATUT_IMAGES,
            self::STATUT_INTERPRETE,
            self::STATUT_VALIDE,
            self::STATUT_ANNULEE,
        ];
    }
}
