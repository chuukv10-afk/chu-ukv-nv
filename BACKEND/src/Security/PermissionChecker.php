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
}
