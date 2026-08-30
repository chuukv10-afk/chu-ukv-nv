<?php

namespace App\Entity;

use App\Repository\PersonnelRoleRepository;
use App\Entity\Trait\UuidV7PrimaryKeyTrait;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: PersonnelRoleRepository::class)]
#[ORM\HasLifecycleCallbacks]
#[ORM\UniqueConstraint(name: 'uniq_personnel_role', columns: ['personnel_id', 'role_id'])]
#[ORM\Table(name: 'personnel_role')]
class PersonnelRole
{
    use UuidV7PrimaryKeyTrait;

    public const PERIMETRE_GLOBAL = 'GLOBAL';
    public const PERIMETRE_DEPARTEMENT = 'DEPARTEMENT';
    public const PERIMETRE_SERVICE = 'SERVICE';

    #[ORM\ManyToOne(inversedBy: 'roleAssignments')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Personnel $personnel = null;

    #[ORM\ManyToOne(inversedBy: 'personnelRoles')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Role $role = null;

    /**
     * Définit où le personnel peut exercer les permissions de ce rôle.
     */
    #[ORM\Column(length: 20)]
    private ?string $perimetre = null;

    #[ORM\ManyToOne]
    private ?Service $service = null;

    #[ORM\ManyToOne]
    private ?Departement $departement = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    /**
     * @return list<string>
     */
    public static function getPerimetres(): array
    {
        return [
            self::PERIMETRE_GLOBAL,
            self::PERIMETRE_DEPARTEMENT,
            self::PERIMETRE_SERVICE,
        ];
    }

    public static function normalizePerimetre(string $perimetre): string
    {
        return strtoupper(trim($perimetre));
    }

    public static function isValidPerimetre(?string $perimetre): bool
    {
        if (null === $perimetre || '' === trim($perimetre)) {
            return false;
        }

        return in_array(self::normalizePerimetre($perimetre), self::getPerimetres(), true);
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

    public function getRole(): ?Role
    {
        return $this->role;
    }

    public function setRole(?Role $role): static
    {
        $this->role = $role;

        return $this;
    }

    public function getPerimetre(): ?string
    {
        return $this->perimetre;
    }

    public function setPerimetre(string $perimetre): static
    {
        $normalizedPerimetre = self::normalizePerimetre($perimetre);

        if (!self::isValidPerimetre($normalizedPerimetre)) {
            throw new \InvalidArgumentException(sprintf(
                'Périmètre invalide "%s". Valeurs autorisées : %s.',
                $perimetre,
                implode(', ', self::getPerimetres())
            ));
        }

        $this->perimetre = $normalizedPerimetre;

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

    public function getDepartement(): ?Departement
    {
        return $this->departement;
    }

    public function setDepartement(?Departement $departement): static
    {
        $this->departement = $departement;

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

    public function validateScope(): void
    {
        if (!$this->hasValidPerimetre()) {
            throw new \InvalidArgumentException('Le périmètre de l\'affectation de rôle est invalide.');
        }

        match ($this->perimetre) {
            self::PERIMETRE_SERVICE => $this->assertScopeReference(
                null !== $this->service,
                'Un service doit être défini pour un périmètre SERVICE.'
            ),
            self::PERIMETRE_DEPARTEMENT => $this->assertScopeReference(
                null !== $this->departement,
                'Un département doit être défini pour un périmètre DEPARTEMENT.'
            ),
            self::PERIMETRE_GLOBAL => $this->assertScopeReference(
                null === $this->service && null === $this->departement,
                'Aucune cible organisationnelle ne doit être définie pour un périmètre GLOBAL.'
            ),
            default => throw new \InvalidArgumentException('Périmètre de rôle non supporté.'),
        };
    }

    public function hasValidPerimetre(): bool
    {
        return self::isValidPerimetre($this->perimetre);
    }

    private function assertScopeReference(bool $condition, string $message): void
    {
        if (!$condition) {
            throw new \InvalidArgumentException($message);
        }
    }
}
