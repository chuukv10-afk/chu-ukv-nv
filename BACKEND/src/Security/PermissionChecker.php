<?php

namespace App\Security;

use App\Entity\Personnel;
use App\Entity\PersonnelRole;
use App\Entity\Role;

/**
 * Vérifie qu'un personnel possède une permission, éventuellement dans le bon périmètre.
 */
final class PermissionChecker
{
    public function __construct(
        private readonly ScopeResolver $scopeResolver,
    ) {
    }

    public function isGranted(Personnel $personnel, string $permissionCode, ?object $subject = null): bool
    {
        $permissionCode = strtolower(trim($permissionCode));
        $scope = $this->scopeResolver->resolve($subject);

        foreach ($personnel->getRoleAssignments() as $assignment) {
            $role = $assignment->getRole();
            if (null === $role || !$this->roleHasPermission($role, $permissionCode)) {
                continue;
            }

            if ($scope->isEmpty() || $this->matchesPerimetre($assignment, $scope)) {
                return true;
            }
        }

        return false;
    }

    public function resolveAccessScope(Personnel $personnel, string $permissionCode): PersonnelAccessScope
    {
        $permissionCode = strtolower(trim($permissionCode));
        $unrestricted = false;
        $serviceIds = [];
        $departementIds = [];

        foreach ($personnel->getRoleAssignments() as $assignment) {
            $role = $assignment->getRole();
            if (null === $role || !$this->roleHasPermission($role, $permissionCode)) {
                continue;
            }

            match ($assignment->getPerimetre()) {
                PersonnelRole::PERIMETRE_GLOBAL => $unrestricted = true,
                PersonnelRole::PERIMETRE_SERVICE => $this->collectServiceScopeId($assignment, $serviceIds),
                PersonnelRole::PERIMETRE_DEPARTEMENT => $this->collectDepartementScopeId($assignment, $departementIds),
                default => null,
            };
        }

        if ($unrestricted) {
            return new PersonnelAccessScope(unrestricted: true);
        }

        return new PersonnelAccessScope(
            serviceIds: array_values(array_unique($serviceIds)),
            departementIds: array_values(array_unique($departementIds)),
        );
    }

    private function roleHasPermission(Role $role, string $permissionCode): bool
    {
        foreach ($role->getPermissions() as $permission) {
            if ($permission->getCode() === $permissionCode) {
                return true;
            }
        }

        return false;
    }

    private function matchesPerimetre(PersonnelRole $assignment, ScopeContext $scope): bool
    {
        return match ($assignment->getPerimetre()) {
            PersonnelRole::PERIMETRE_GLOBAL => true,
            PersonnelRole::PERIMETRE_DEPARTEMENT => null !== $scope->departement
                && null !== $assignment->getDepartement()
                && $assignment->getDepartement()->getId() === $scope->departement->getId(),
            PersonnelRole::PERIMETRE_SERVICE => $this->matchesServicePerimetre($assignment, $scope),
            default => false,
        };
    }

    private function matchesServicePerimetre(PersonnelRole $assignment, ScopeContext $scope): bool
    {
        $assignedService = $assignment->getService();
        if (null === $assignedService) {
            return false;
        }

        if (null !== $scope->service) {
            return $assignedService->getId() === $scope->service->getId();
        }

        if (null !== $scope->departement) {
            return $assignedService->getDepartement()?->getId() === $scope->departement->getId();
        }

        return false;
    }

    /**
     * @param list<int> $serviceIds
     */
    private function collectServiceScopeId(PersonnelRole $assignment, array &$serviceIds): void
    {
        $serviceId = $assignment->getService()?->getId();
        if (null !== $serviceId) {
            $serviceIds[] = $serviceId;
        }
    }

    /**
     * @param list<int> $departementIds
     */
    private function collectDepartementScopeId(PersonnelRole $assignment, array &$departementIds): void
    {
        $departementId = $assignment->getDepartement()?->getId();
        if (null !== $departementId) {
            $departementIds[] = $departementId;
        }
    }
}
