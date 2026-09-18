<?php

namespace App\Entity;

use App\Repository\OrganisationPartenaireRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: OrganisationPartenaireRepository::class)]
#[ORM\Table(name: 'organisation_partenaire')]
#[ORM\UniqueConstraint(name: 'UNIQ_ORG_PARTENAIRE_CODE', columns: ['code'])]
class OrganisationPartenaire
{
    public const TYPE_UNIVERSITE = 'UNIVERSITE';
    public const TYPE_INSTITUT_SUPERIEUR = 'INSTITUT_SUPERIEUR';
    public const TYPE_ECOLE = 'ECOLE';
    public const TYPE_ENTREPRISE = 'ENTREPRISE';
    public const TYPE_ONG = 'ONG';
    public const TYPE_ADMINISTRATION = 'ADMINISTRATION';
    public const TYPE_AUTRE = 'AUTRE';

    public const STATUT_ACTIF = 'ACTIF';
    public const STATUT_INACTIF = 'INACTIF';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 15)]
    private ?string $code = null;

    #[ORM\Column(length: 150)]
    private ?string $libelle = null;

    #[ORM\Column(length: 30)]
    private ?string $typeInstitution = null;

    #[ORM\Column(length: 20)]
    private string $statut = self::STATUT_ACTIF;

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    /**
     * @var Collection<int, Filiere>
     */
    #[ORM\OneToMany(targetEntity: Filiere::class, mappedBy: 'organisation')]
    private Collection $filieres;

    /**
     * @var Collection<int, Patient>
     */
    #[ORM\OneToMany(targetEntity: Patient::class, mappedBy: 'organisation')]
    private Collection $patients;

    public function __construct()
    {
        $this->filieres = new ArrayCollection();
        $this->patients = new ArrayCollection();
    }

    /**
     * @return list<string>
     */
    public static function getTypesInstitution(): array
    {
        return [
            self::TYPE_UNIVERSITE,
            self::TYPE_INSTITUT_SUPERIEUR,
            self::TYPE_ECOLE,
            self::TYPE_ENTREPRISE,
            self::TYPE_ONG,
            self::TYPE_ADMINISTRATION,
            self::TYPE_AUTRE,
        ];
    }

    /**
     * @return list<string>
     */
    public static function getStatuts(): array
    {
        return [self::STATUT_ACTIF, self::STATUT_INACTIF];
    }

    public static function normalizeType(string $type): string
    {
        return strtoupper(trim($type));
    }

    public static function isValidType(?string $type): bool
    {
        if (null === $type || '' === trim($type)) {
            return false;
        }

        return in_array(self::normalizeType($type), self::getTypesInstitution(), true);
    }

    public function isUniversite(): bool
    {
        return self::TYPE_UNIVERSITE === $this->typeInstitution;
    }

    public function requiresFiliere(): bool
    {
        return $this->isUniversite();
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

    public function getTypeInstitution(): ?string
    {
        return $this->typeInstitution;
    }

    public function setTypeInstitution(string $typeInstitution): static
    {
        $this->typeInstitution = $typeInstitution;

        return $this;
    }

    public function getStatut(): string
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

    /**
     * @return Collection<int, Filiere>
     */
    public function getFilieres(): Collection
    {
        return $this->filieres;
    }

    /**
     * @return Collection<int, Patient>
     */
    public function getPatients(): Collection
    {
        return $this->patients;
    }
}
