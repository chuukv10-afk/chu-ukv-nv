<?php

namespace App\Entity;

use App\Repository\TriageMesureRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: TriageMesureRepository::class)]
class TriageMesure
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 50)]
    private ?string $valeur = null;

    #[ORM\ManyToOne(inversedBy: 'mesures')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Triage $triage = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?SigneVital $signeVital = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getValeur(): ?string
    {
        return $this->valeur;
    }

    public function setValeur(string $valeur): static
    {
        $this->valeur = $valeur;

        return $this;
    }

    public function getTriage(): ?Triage
    {
        return $this->triage;
    }

    public function setTriage(?Triage $triage): static
    {
        $this->triage = $triage;

        return $this;
    }

    public function getSigneVital(): ?SigneVital
    {
        return $this->signeVital;
    }

    public function setSigneVital(SigneVital $signeVital): static
    {
        $this->signeVital = $signeVital;

        return $this;
    }
}
