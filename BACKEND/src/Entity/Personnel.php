<?php

namespace App\Entity;

use App\Entity\Trait\UuidV7PrimaryKeyTrait;
use App\Repository\PersonnelRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;

#[ORM\Entity(repositoryClass: PersonnelRepository::class)]
#[ORM\HasLifecycleCallbacks]
class Personnel implements UserInterface, PasswordAuthenticatedUserInterface
{
    use UuidV7PrimaryKeyTrait;

    public const STATUS_ACTIF = 'ACTIF';
    public const STATUS_INACTIF = 'INACTIF';
    public const STATUS_SUSPENDU = 'SUSPENDU';
    public const STATUS_RETRAITE = 'RETRAITE';
    public const STATUS_DECESE = 'MORTE';
    public const STATUS_DEMISSION = 'DEMISSION';
    public const STATUS_SUPPRIME = 'SUPPRIME';

    #[ORM\Column(length: 50)]
    private ?string $nom = null;

    #[ORM\Column(length: 50)]
    private ?string $postNom = null;

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $prenom = null;

    #[ORM\Column(length: 15, unique: true)]
    private ?string $telephone = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $adresse = null;

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $lieuNaissance = null;

    #[ORM\Column(length: 1)]
    private ?string $sexe = null;

    #[ORM\Column(length: 255)]
    private ?string $password = null;

    #[ORM\Column(length: 20)]
    private ?string $status = null;

    #[ORM\Column(length: 20, unique: true)]
    private ?string $matricule = null;

    #[ORM\Column(length: 20, nullable: true)]
    private ?string $cnome = null;

    #[ORM\Column(length: 15)]
    private ?string $type = null;

    #[ORM\ManyToOne(inversedBy: 'personnels')]
    private ?Grade $grade = null;

    #[ORM\ManyToOne(inversedBy: 'personnels')]
    private ?Service $service = null;

    /**
     * @var Collection<int, PersonnelRole>
     */
    #[ORM\OneToMany(targetEntity: PersonnelRole::class, mappedBy: 'personnel', orphanRemoval: true, cascade: ['persist', 'remove'])]
    private Collection $roleAssignments;

    /**
     * @var Collection<int, Specialite>
     */
    #[ORM\ManyToMany(targetEntity: Specialite::class, inversedBy: 'personnels')]
    private Collection $specialites;

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    public function __construct()
    {
        $this->roleAssignments = new ArrayCollection();
        $this->specialites = new ArrayCollection();
    }

    public function getNom(): ?string
    {
        return $this->nom;
    }

    public function setNom(string $nom): static
    {
        $this->nom = $nom;

        return $this;
    }

    public function getPostNom(): ?string
    {
        return $this->postNom;
    }

    public function setPostNom(string $postNom): static
    {
        $this->postNom = $postNom;

        return $this;
    }

    public function getPrenom(): ?string
    {
        return $this->prenom;
    }

    public function setPrenom(?string $prenom): static
    {
        $this->prenom = $prenom;

        return $this;
    }

    public function getTelephone(): ?string
    {
        return $this->telephone;
    }

    public function setTelephone(string $telephone): static
    {
        $this->telephone = $telephone;

        return $this;
    }

    public function getAdresse(): ?string
    {
        return $this->adresse;
    }

    public function setAdresse(?string $adresse): static
    {
        $this->adresse = $adresse;

        return $this;
    }

    public function getLieuNaissance(): ?string
    {
        return $this->lieuNaissance;
    }

    public function setLieuNaissance(?string $lieuNaissance): static
    {
        $this->lieuNaissance = $lieuNaissance;

        return $this;
    }

    public function getSexe(): ?string
    {
        return $this->sexe;
    }

    public function setSexe(string $sexe): static
    {
        $this->sexe = $sexe;

        return $this;
    }

    public function getPassword(): ?string
    {
        return $this->password;
    }

    public function setPassword(string $password): static
    {
        $this->password = $password;

        return $this;
    }

    public function getStatus(): ?string
    {
        return $this->status;
    }

    public function setStatus(string $status): static
    {
        $normalizedStatus = self::normalizeStatus($status);

        if (!self::isValidStatus($normalizedStatus)) {
            throw new \InvalidArgumentException(sprintf(
                'Statut personnel invalide "%s". Valeurs autorisées : %s.',
                $status,
                implode(', ', self::getStatuses())
            ));
        }

        $this->status = $normalizedStatus;

        return $this;
    }

    public function getMatricule(): ?string
    {
        return $this->matricule;
    }

    public function setMatricule(string $matricule): static
    {
        $this->matricule = $matricule;

        return $this;
    }

    public function getCnome(): ?string
    {
        return $this->cnome;
    }

    public function setCnome(?string $cnome): static
    {
        $this->cnome = $cnome;

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

    public function getGrade(): ?Grade
    {
        return $this->grade;
    }

    public function setGrade(?Grade $grade): static
    {
        $this->grade = $grade;

        return $this;
    }

    public function getService(): ?Service
    {
        return $this->service;
    }

    public function setService(?Service $service): static
    {
        $this->service = $service;

        return $this;
    }

    /**
     * @return Collection<int, PersonnelRole>
     */
    public function getRoleAssignments(): Collection
    {
        return $this->roleAssignments;
    }

    public function assignRole(
        Role $role,
        ?Service $service = null,
        ?Departement $departement = null,
    ): PersonnelRole {
        foreach ($this->roleAssignments as $assignment) {
            if ($assignment->getRole()?->getCode() === $role->getCode()) {
                throw new \InvalidArgumentException(sprintf('Le rôle "%s" est déjà assigné à ce personnel.', $role->getCode()));
            }
        }

        $perimetre = (string) $role->getPerimetre();
        if ('' === $perimetre) {
            throw new \InvalidArgumentException(sprintf('Le rôle "%s" n\'a pas de périmètre défini.', $role->getCode()));
        }

        $resolvedService = match ($perimetre) {
            PersonnelRole::PERIMETRE_SERVICE => $service ?? $this->service,
            default => null,
        };
        $resolvedDepartement = match ($perimetre) {
            PersonnelRole::PERIMETRE_DEPARTEMENT => $departement,
            default => null,
        };

        $assignment = (new PersonnelRole())
            ->setPersonnel($this)
            ->setRole($role)
            ->setPerimetre($perimetre)
            ->setService($resolvedService)
            ->setDepartement($resolvedDepartement)
            ->setCreatedAt(new \DateTimeImmutable());

        $assignment->validateScope();
        $this->roleAssignments->add($assignment);
        $role->addPersonnelRole($assignment);

        return $assignment;
    }

    public function removeRoleAssignment(PersonnelRole $assignment): static
    {
        if ($this->roleAssignments->removeElement($assignment)) {
            if ($assignment->getPersonnel() === $this) {
                $assignment->setPersonnel(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, Role>
     */
    public function getAssignedRoles(): Collection
    {
        return new ArrayCollection(
            array_values(array_filter(array_map(
                static fn (PersonnelRole $assignment): ?Role => $assignment->getRole(),
                $this->roleAssignments->toArray()
            )))
        );
    }

    public function addAssignedRole(
        Role $role,
        ?Service $service = null,
        ?Departement $departement = null,
    ): PersonnelRole {
        return $this->assignRole($role, $service, $departement);
    }

    public function removeAssignedRole(Role $role): static
    {
        foreach ($this->roleAssignments as $assignment) {
            if ($assignment->getRole()?->getCode() === $role->getCode()) {
                $this->removeRoleAssignment($assignment);
                break;
            }
        }

        return $this;
    }

    public function getUserIdentifier(): string
    {
        return (string) $this->telephone;
    }

    /**
     * @return list<string>
     */
    public function getRoles(): array
    {
        $symfonyRoles = [];

        foreach ($this->roleAssignments as $assignment) {
            $role = $assignment->getRole();
            if (null === $role) {
                continue;
            }

            $symfonyRoles[] = self::buildSymfonyRoleCode((string) $role->getCode());

            foreach ($role->getPermissions() as $permission) {
                $symfonyRoles[] = (string) $permission->getCode();
            }
        }

        return array_values(array_unique($symfonyRoles));
    }

    public static function buildSymfonyRoleCode(string $code): string
    {
        $normalizedCode = strtoupper(trim($code));

        return str_starts_with($normalizedCode, 'ROLE_') ? $normalizedCode : 'ROLE_' . $normalizedCode;
    }

    /**
     * @return list<array{role: string, perimetre: string|null, service: string|null, departement: string|null}>
     */
    public function getRoleAssignmentSummary(): array
    {
        $summary = [];

        foreach ($this->roleAssignments as $assignment) {
            $summary[] = [
                'role' => (string) $assignment->getRole()?->getCode(),
                'perimetre' => $assignment->getPerimetre(),
                'service' => $assignment->getService()?->getLibelle(),
                'departement' => $assignment->getDepartement()?->getLibelle(),
            ];
        }

        return $summary;
    }

    public function eraseCredentials(): void
    {
    }

    /**
     * @return list<string>
     */
    public static function getStatuses(): array
    {
        return [
            self::STATUS_ACTIF,
            self::STATUS_INACTIF,
            self::STATUS_SUSPENDU,
            self::STATUS_DECESE,
            self::STATUS_RETRAITE,
            self::STATUS_DEMISSION,
            self::STATUS_SUPPRIME,
        ];
    }

    public static function normalizeStatus(string $status): string
    {
        return strtoupper(trim($status));
    }

    public static function isValidStatus(?string $status): bool
    {
        if (null === $status || '' === trim($status)) {
            return false;
        }

        return in_array(self::normalizeStatus($status), self::getStatuses(), true);
    }

    public function hasValidStatus(): bool
    {
        return self::isValidStatus($this->status);
    }

    public function canAuthenticate(): bool
    {
        return $this->hasValidStatus() && self::STATUS_ACTIF === self::normalizeStatus((string) $this->status);
    }

    public function isActive(): bool
    {
        return $this->canAuthenticate();
    }

    public function getAuthenticationDeniedMessage(): string
    {
        if (null === $this->status || '' === trim($this->status)) {
            return 'Statut de compte non défini. Contactez l\'administration.';
        }

        if (!$this->hasValidStatus()) {
            return 'Statut de compte invalide. Contactez l\'administration.';
        }

        return match (self::normalizeStatus($this->status)) {
            self::STATUS_INACTIF => 'Votre compte n\'est pas actif. Contactez l\'administration.',
            self::STATUS_SUSPENDU => 'Votre compte est suspendu temporairement.',
            self::STATUS_RETRAITE => 'Votre compte est clôturé (retraite).',
            self::STATUS_DEMISSION => 'Votre compte est clôturé (démission).',
            self::STATUS_DECESE => 'Compte clôturé.',
            self::STATUS_SUPPRIME => 'Votre compte a été définitivement exclu.',
            default => 'Statut de compte invalide. Contactez l\'administration.',
        };
    }

    /**
     * @return Collection<int, Specialite>
     */
    public function getSpecialites(): Collection
    {
        return $this->specialites;
    }

    public function addSpecialite(Specialite $specialite): static
    {
        if (!$this->specialites->contains($specialite)) {
            $this->specialites->add($specialite);
        }

        return $this;
    }

    public function removeSpecialite(Specialite $specialite): static
    {
        $this->specialites->removeElement($specialite);

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
