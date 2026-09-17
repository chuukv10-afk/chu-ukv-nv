<?php

namespace App\Entity;

use App\Repository\PaieLigneRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: PaieLigneRepository::class)]
#[ORM\Table(name: 'paie_ligne')]
#[ORM\UniqueConstraint(name: 'UNIQ_PAIE_LIGNE_PERIODE_PERSONNEL', columns: ['periode_id', 'personnel_id'])]
class PaieLigne
{
    public const SOURCE_BAREME = 'BAREME';
    public const SOURCE_MANUEL = 'MANUEL';
    public const SOURCE_AUCUN = 'AUCUN';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'lignes')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?PaiePeriode $periode = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Personnel $personnel = null;

    #[ORM\Column(length: 160)]
    private ?string $nomComplet = null;

    #[ORM\Column(length: 20, nullable: true)]
    private ?string $matricule = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $gradeLibelle = null;

    #[ORM\Column(length: 150, nullable: true)]
    private ?string $fonctionLibelle = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $serviceLibelle = null;

    #[ORM\Column(length: 20, nullable: true)]
    private ?string $statutPersonnel = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 12, scale: 2)]
    private string $montant = '0.00';

    #[ORM\Column(type: Types::DECIMAL, precision: 12, scale: 2, nullable: true)]
    private ?string $montantPropose = null;

    #[ORM\Column(length: 20)]
    private string $source = self::SOURCE_AUCUN;

    #[ORM\Column]
    private bool $inclus = false;

    #[ORM\Column(length: 30, nullable: true)]
    private ?string $motifCode = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $motif = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getPeriode(): ?PaiePeriode
    {
        return $this->periode;
    }

    public function setPeriode(?PaiePeriode $periode): static
    {
        $this->periode = $periode;

        return $this;
    }

    public function getPersonnel(): ?Personnel
    {
        return $this->personnel;
    }

    public function setPersonnel(?Personnel $personnel): static
    {
        $this->personnel = $personnel;

        return $this;
    }

    public function getNomComplet(): ?string
    {
        return $this->nomComplet;
    }

    public function setNomComplet(string $nomComplet): static
    {
        $this->nomComplet = $nomComplet;

        return $this;
    }

    public function getMatricule(): ?string
    {
        return $this->matricule;
    }

    public function setMatricule(?string $matricule): static
    {
        $this->matricule = $matricule;

        return $this;
    }

    public function getGradeLibelle(): ?string
    {
        return $this->gradeLibelle;
    }

    public function setGradeLibelle(?string $gradeLibelle): static
    {
        $this->gradeLibelle = $gradeLibelle;

        return $this;
    }

    public function getFonctionLibelle(): ?string
    {
        return $this->fonctionLibelle;
    }

    public function setFonctionLibelle(?string $fonctionLibelle): static
    {
        $this->fonctionLibelle = $fonctionLibelle;

        return $this;
    }

    public function getServiceLibelle(): ?string
    {
        return $this->serviceLibelle;
    }

    public function setServiceLibelle(?string $serviceLibelle): static
    {
        $this->serviceLibelle = $serviceLibelle;

        return $this;
    }

    public function getStatutPersonnel(): ?string
    {
        return $this->statutPersonnel;
    }

    public function setStatutPersonnel(?string $statutPersonnel): static
    {
        $this->statutPersonnel = $statutPersonnel;

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

    public function getMontantPropose(): ?string
    {
        return $this->montantPropose;
    }

    public function setMontantPropose(?string $montantPropose): static
    {
        $this->montantPropose = $montantPropose;

        return $this;
    }

    public function getSource(): string
    {
        return $this->source;
    }

    public function setSource(string $source): static
    {
        $this->source = $source;

        return $this;
    }

    public function isInclus(): bool
    {
        return $this->inclus;
    }

    public function setInclus(bool $inclus): static
    {
        $this->inclus = $inclus;

        return $this;
    }

    public function getMotifCode(): ?string
    {
        return $this->motifCode;
    }

    public function setMotifCode(?string $motifCode): static
    {
        $this->motifCode = $motifCode;

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
