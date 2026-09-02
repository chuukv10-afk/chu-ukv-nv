<?php

namespace App\Service\Role;

use App\Entity\Permission;
use App\Entity\PersonnelRole;
use App\Entity\Role;
use App\Repository\PermissionRepository;
use App\Security\Permission\AdminPermissions;
use App\Security\Permission\CliniquePermissions;
use App\Security\Permission\OrganisationPermissions;
use App\Security\Permission\ReferentielPermissions;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Crée les permissions en base et les lie aux rôles.
 */
final class PermissionProvisioner
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly PermissionRepository $permissionRepository,
        private readonly RoleProvisioner $roleProvisioner,
    ) {
    }

    public function syncDepartementPermissions(): int
    {
        return $this->syncPermissions(OrganisationPermissions::departementDefinitions());
    }

    public function syncAll(): int
    {
        $definitions = array_merge(
            OrganisationPermissions::allDefinitions(),
            ReferentielPermissions::allDefinitions(),
            CliniquePermissions::allDefinitions(),
            AdminPermissions::allDefinitions(),
        );

        return $this->syncPermissions($definitions);
    }

    /**
     * @param list<array{code: string, libelle: string, module: string}> $definitions
     */
    private function syncPermissions(array $definitions): int
    {
        $createdCount = 0;

        foreach ($definitions as $definition) {
            if ($this->findOrCreatePermission($definition)) {
                ++$createdCount;
            }
        }

        $this->entityManager->flush();

        $adminRole = $this->roleProvisioner->findOrCreate(
            Role::CODE_ADMIN,
            'Administrateur',
            PersonnelRole::PERIMETRE_GLOBAL,
        );
        $personnelRole = $this->roleProvisioner->findOrCreate(
            Role::CODE_PERSONNEL,
            'Personnel',
            PersonnelRole::PERIMETRE_SERVICE,
        );

        foreach ($definitions as $definition) {
            $permission = $this->permissionRepository->findOneBy(['code' => $definition['code']]);
            if (null === $permission) {
                continue;
            }

            $adminRole->addPermission($permission);

            if (str_ends_with($definition['code'], '.read')) {
                $personnelRole->addPermission($permission);
            }
        }

        $this->entityManager->flush();

        return $createdCount;
    }

    /**
     * @param array{code: string, libelle: string, module: string} $definition
     */
    private function findOrCreatePermission(array $definition): bool
    {
        $existing = $this->permissionRepository->findOneBy(['code' => $definition['code']]);
        if (null !== $existing) {
            return false;
        }

        $permission = (new Permission())
            ->setCode($definition['code'])
            ->setLibelle($definition['libelle'])
            ->setModule($definition['module'])
            ->setCreatedAt(new \DateTimeImmutable());

        $this->entityManager->persist($permission);

        return true;
    }
}
