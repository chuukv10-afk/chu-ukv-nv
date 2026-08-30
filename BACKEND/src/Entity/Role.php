<?php

namespace App\Entity;

use App\Entity\Trait\UuidV7PrimaryKeyTrait;
use App\Repository\RoleRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: RoleRepository::class)]
#[ORM\HasLifecycleCallbacks]
class Role
{
    use UuidV7PrimaryKeyTrait;

    public const CODE_ADMIN = 'ADMIN';
    public const CODE_PERSONNEL = 'PERSONNEL';

    #[ORM\Column(length: 20, unique: true)]
    private ?string $code = null;

    #[ORM\Column(length: 100)]
    private ?string $libelle = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    /**
     * @var Collection<int, PersonnelRole>
     */
    #[ORM\OneToMany(targetEntity: PersonnelRole::class, mappedBy: 'role', orphanRemoval: true)]
    private Collection $personnelRoles;

    /**
     * @var Collection<int, Permission>
     */
    #[ORM\ManyToMany(targetEntity: Permission::class, inversedBy: 'roles')]
    #[ORM\JoinTable(name: 'role_permission')]
    private Collection $permissions;

    public function __construct()
    {
        $this->personnelRoles = new ArrayCollection();
        $this->permissions = new ArrayCollection();
    }

    public function getCode(): ?string
    {
        return $this->code;
    }

    public function setCode(string $code): static
    {
        $this->code = strtoupper(trim($code));

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
     * @return Collection<int, PersonnelRole>
     */
    public function getPersonnelRoles(): Collection
    {
        return $this->personnelRoles;
    }

    public function addPersonnelRole(PersonnelRole $personnelRole): static
    {
        if (!$this->personnelRoles->contains($personnelRole)) {
            $this->personnelRoles->add($personnelRole);
            $personnelRole->setRole($this);
        }

        return $this;
    }

    public function removePersonnelRole(PersonnelRole $personnelRole): static
    {
        if ($this->personnelRoles->removeElement($personnelRole)) {
            if ($personnelRole->getRole() === $this) {
                $personnelRole->setRole(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, Permission>
     */
    public function getPermissions(): Collection
    {
        return $this->permissions;
    }

    public function addPermission(Permission $permission): static
    {
        if (!$this->permissions->contains($permission)) {
            $this->permissions->add($permission);
        }

        return $this;
    }

    public function removePermission(Permission $permission): static
    {
        $this->permissions->removeElement($permission);

        return $this;
    }
}
