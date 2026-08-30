<?php

namespace App\Service\Role;

use App\Entity\Departement;
use App\Entity\Personnel;
use App\Entity\PersonnelRole;
use App\Entity\Role;
use App\Entity\Service;
use App\Repository\RoleRepository;
use Doctrine\ORM\EntityManagerInterface;

final class RoleProvisioner
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly RoleRepository $roleRepository,
    ) {
    }

    public function findOrCreate(string $code, string $libelle): Role
    {
        $normalizedCode = strtoupper(trim($code));
        $role = $this->roleRepository->findOneBy(['code' => $normalizedCode]);

        if (null !== $role) {
            return $role;
        }

        $role = (new Role())
            ->setCode($normalizedCode)
            ->setLibelle($libelle)
            ->setCreatedAt(new \DateTimeImmutable());

        $this->entityManager->persist($role);

        return $role;
    }

    public function hasRole(Personnel $personnel, string $code): bool
    {
        $normalizedCode = strtoupper(trim($code));

        foreach ($personnel->getRoleAssignments() as $assignment) {
            if ($assignment->getRole()?->getCode() === $normalizedCode) {
                return true;
            }
        }

        return false;
    }

    public function assign(
        Personnel $personnel,
        Role $role,
        string $perimetre,
        ?Service $service = null,
        ?Departement $departement = null,
    ): PersonnelRole {
        if ($this->hasRole($personnel, (string) $role->getCode())) {
            foreach ($personnel->getRoleAssignments() as $assignment) {
                if ($assignment->getRole()?->getCode() === $role->getCode()) {
                    return $assignment;
                }
            }
        }

        return $personnel->assignRole($role, $perimetre, $service, $departement);
    }

    public function assignDefaultPersonnelRole(Personnel $personnel): PersonnelRole
    {
        $role = $this->findOrCreate(Role::CODE_PERSONNEL, 'Personnel');

        if (null !== $personnel->getService()) {
            return $this->assign(
                $personnel,
                $role,
                PersonnelRole::PERIMETRE_SERVICE,
                $personnel->getService(),
            );
        }

        return $this->assign($personnel, $role, PersonnelRole::PERIMETRE_GLOBAL);
    }

    public function assignAdminRole(Personnel $personnel): PersonnelRole
    {
        $role = $this->findOrCreate(Role::CODE_ADMIN, 'Administrateur');

        return $this->assign($personnel, $role, PersonnelRole::PERIMETRE_GLOBAL);
    }
}
