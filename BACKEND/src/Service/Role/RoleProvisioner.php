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

    public function findOrCreate(string $code, string $libelle, string $perimetre): Role
    {
        $normalizedCode = strtoupper(trim($code));
        $role = $this->roleRepository->findOneBy(['code' => $normalizedCode]);

        if (null !== $role) {
            if (null === $role->getPerimetre()) {
                $role->setPerimetre($perimetre);
            }

            return $role;
        }

        $role = (new Role())
            ->setCode($normalizedCode)
            ->setLibelle($libelle)
            ->setPerimetre($perimetre)
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

        return $personnel->assignRole($role, $service, $departement);
    }

    public function assignDefaultPersonnelRole(Personnel $personnel): ?PersonnelRole
    {
        $role = $this->findOrCreate(
            Role::CODE_PERSONNEL,
            'Personnel',
            PersonnelRole::PERIMETRE_SERVICE,
        );

        if (PersonnelRole::PERIMETRE_SERVICE === $role->getPerimetre() && null === $personnel->getService()) {
            return null;
        }

        return $this->assign($personnel, $role, $personnel->getService());
    }

    public function assignAdminRole(Personnel $personnel): PersonnelRole
    {
        $role = $this->findOrCreate(
            Role::CODE_ADMIN,
            'Administrateur',
            PersonnelRole::PERIMETRE_GLOBAL,
        );

        return $this->assign($personnel, $role);
    }
}
