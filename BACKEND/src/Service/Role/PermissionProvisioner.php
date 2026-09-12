<?php

namespace App\Service\Role;

use App\Entity\Permission;
use App\Entity\PersonnelRole;
use App\Entity\Role;
use App\Repository\PermissionRepository;
use App\Security\Permission\AdminPermissions;
use App\Security\Permission\CliniquePermissions;
use App\Security\Permission\OrganisationPermissions;
use App\Security\Permission\PatientPermissions;
use App\Security\Permission\IntendancePermissions;
use App\Security\Permission\PharmaciePermissions;
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
            PatientPermissions::allDefinitions(),
            PharmaciePermissions::allDefinitions(),
            IntendancePermissions::allDefinitions(),
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
            PersonnelRole::PERIMETRE_GLOBAL,
        );

        foreach ($definitions as $definition) {
            $permission = $this->permissionRepository->findOneBy(['code' => $definition['code']]);
            if (null === $permission) {
                continue;
            }

            $adminRole->addPermission($permission);

            if (str_ends_with($definition['code'], '.read') || self::isPersonnelSelfService($definition['code'])) {
                $personnelRole->addPermission($permission);
            }
        }

        $this->grantNewPermissionsToMatchingRoles($definitions);

        $this->entityManager->flush();

        return $createdCount;
    }

    /**
     * Accorde une nouvelle permission aux rôles métier qui ont déjà
     * une permission du même module et de la même action (ex. signe_vital.read → plainte.read).
     *
     * @param list<array{code: string, libelle: string, module: string}> $definitions
     */
    private function grantNewPermissionsToMatchingRoles(array $definitions): void
    {
        $roles = $this->entityManager->getRepository(Role::class)->findAll();

        foreach ($roles as $role) {
            if (in_array($role->getCode(), [Role::CODE_ADMIN, Role::CODE_PERSONNEL], true)) {
                continue;
            }

            $ownedCodes = [];
            foreach ($role->getPermissions() as $owned) {
                $ownedCodes[] = (string) $owned->getCode();
            }

            foreach ($definitions as $definition) {
                $code = $definition['code'];
                $action = substr($code, (int) strrpos($code, '.') + 1);
                $hasSameActionInModule = false;

                foreach ($ownedCodes as $ownedCode) {
                    if (!str_starts_with($ownedCode, 'referentiel.') && Permission::MODULE_REFERENTIEL === $definition['module']) {
                        continue;
                    }

                    if ($definition['module'] === Permission::MODULE_REFERENTIEL
                        && str_starts_with($ownedCode, 'referentiel.')
                        && str_ends_with($ownedCode, '.' . $action)
                    ) {
                        $hasSameActionInModule = true;
                        break;
                    }
                }

                if (!$hasSameActionInModule) {
                    continue;
                }

                $permission = $this->permissionRepository->findOneBy(['code' => $code]);
                if (null !== $permission) {
                    $role->addPermission($permission);
                }
            }
        }
    }

    private static function isPersonnelSelfService(string $code): bool
    {
        return AdminPermissions::SIGNATURE_UPDATE === $code;
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
