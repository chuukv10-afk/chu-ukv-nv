<?php

namespace App\Entity;

use App\Entity\Trait\UuidV7PrimaryKeyTrait;
use App\Repository\PermissionRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: PermissionRepository::class)]
#[ORM\HasLifecycleCallbacks]
class Permission
{
    use UuidV7PrimaryKeyTrait;

    public const MODULE_PATIENT = 'PATIENT';
    public const MODULE_CLINIQUE = 'CLINIQUE';
    public const MODULE_FACTURATION = 'FACTURATION';
    public const MODULE_ORGANISATION = 'ORGANISATION';
    public const MODULE_REFERENTIEL = 'REFERENTIEL';
    public const MODULE_ADMIN = 'ADMIN';

    #[ORM\Column(length: 50, unique: true)]
    private ?string $code = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $libelle = null;

    #[ORM\Column(length: 300, nullable: true)]
    private ?string $description = null;

    /**
     * Regroupe la permission par domaine fonctionnel (QUOI), pas le périmètre géographique.
     */
    #[ORM\Column(length: 30)]
    private ?string $module = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    /**
     * @var Collection<int, Role>
     */
    #[ORM\ManyToMany(targetEntity: Role::class, mappedBy: 'permissions')]
    private Collection $roles;

    public function __construct()
    {
        $this->roles = new ArrayCollection();
    }

    /**
     * @return list<string>
     */
    public static function getModules(): array
    {
        return [
            self::MODULE_PATIENT,
            self::MODULE_CLINIQUE,
            self::MODULE_FACTURATION,
            self::MODULE_ORGANISATION,
            self::MODULE_REFERENTIEL,
            self::MODULE_ADMIN,
        ];
    }

    public static function normalizeModule(string $module): string
    {
        return strtoupper(trim($module));
    }

    public static function isValidModule(?string $module): bool
    {
        if (null === $module || '' === trim($module)) {
            return false;
        }

        return in_array(self::normalizeModule($module), self::getModules(), true);
    }

    public function getCode(): ?string
    {
        return $this->code;
    }

    public function setCode(string $code): static
    {
        $this->code = strtolower(trim($code));

        return $this;
    }

    public function getLibelle(): ?string
    {
        return $this->libelle;
    }

    public function setLibelle(?string $libelle): static
    {
        $this->libelle = $libelle;

        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): static
    {
        $this->description = $description;

        return $this;
    }

    public function getModule(): ?string
    {
        return $this->module;
    }

    public function setModule(string $module): static
    {
        $normalizedModule = self::normalizeModule($module);

        if (!self::isValidModule($normalizedModule)) {
            throw new \InvalidArgumentException(sprintf(
                'Module de permission invalide "%s". Valeurs autorisées : %s.',
                $module,
                implode(', ', self::getModules())
            ));
        }

        $this->module = $normalizedModule;

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
     * @return Collection<int, Role>
     */
    public function getRoles(): Collection
    {
        return $this->roles;
    }

    public function addRole(Role $role): static
    {
        if (!$this->roles->contains($role)) {
            $this->roles->add($role);
            $role->addPermission($this);
        }

        return $this;
    }

    public function removeRole(Role $role): static
    {
        if ($this->roles->removeElement($role)) {
            $role->removePermission($this);
        }

        return $this;
    }
}
