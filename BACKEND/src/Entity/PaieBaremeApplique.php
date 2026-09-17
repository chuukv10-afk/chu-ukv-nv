<?php

namespace App\Entity;

use App\Repository\PaieBaremeAppliqueRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: PaieBaremeAppliqueRepository::class)]
#[ORM\Table(name: 'paie_bareme_applique')]
class PaieBaremeApplique
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'baremesAppliques')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?PaiePeriode $periode = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Grade $grade = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Fonction $fonction = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $gradeLibelle = null;

    #[ORM\Column(length: 150)]
    private ?string $fonctionLibelle = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 12, scale: 2)]
    private string $montant = '0.00';

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

    public function getGrade(): ?Grade
    {
        return $this->grade;
    }

    public function setGrade(?Grade $grade): static
    {
        $this->grade = $grade;

        return $this;
    }

    public function getFonction(): ?Fonction
    {
        return $this->fonction;
    }

    public function setFonction(?Fonction $fonction): static
    {
        $this->fonction = $fonction;

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

    public function setFonctionLibelle(string $fonctionLibelle): static
    {
        $this->fonctionLibelle = $fonctionLibelle;

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
}
