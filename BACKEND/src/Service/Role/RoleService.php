<?php

namespace App\Service\Role;

use App\DTO\Admin\AssignRolePermissionsInput;
use App\DTO\Admin\CreateRoleInput;
use App\DTO\Admin\CreateRolePermissionAssignmentInput;
use App\DTO\Admin\RolePermissionListQuery;
use App\DTO\Admin\UpdateRoleInput;
use App\DTO\Common\PaginatedResult;
use App\Entity\Permission;
use App\Entity\PersonnelRole;
use App\Entity\Role;
use App\Exception\ConflictException;
use App\Exception\NotFoundException;
use App\Repository\PermissionRepository;
use App\Repository\RoleRepository;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Exception\ValidationFailedException;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final class RoleService
{
    private const SYSTEM_ROLE_CODES = [
        Role::CODE_ADMIN,
        Role::CODE_PERSONNEL,
    ];

    public function __construct(
        private readonly EntityManagerInterface $eM,
        private readonly RoleRepository $roleRepository,
        private readonly PermissionRepository $permissionRepository,
        private readonly ValidatorInterface $validator,
    ) {
    }

    /**
     * @return list<Role>
     */
    public function findAll(): array
    {
        return $this->roleRepository->findBy([], ['code' => 'ASC']);
    }

    public function getById(string $id): Role
    {
        if (!Uuid::isValid($id)) {
            throw new NotFoundException('Rôle non trouvé.');
        }

        $role = $this->roleRepository->find(Uuid::fromString($id));
        if (null === $role) {
            throw new NotFoundException('Rôle non trouvé.');
        }

        return $role;
    }

    public function create(CreateRoleInput $input): Role
    {
        $errors = $this->validator->validate($input);
        if (count($errors) > 0) {
            throw new ValidationFailedException($input, $errors);
        }

        $normalizedCode = strtoupper(trim($input->code));
        if ($this->roleRepository->findOneBy(['code' => $normalizedCode])) {
            throw new ConflictException('Ce code rôle existe déjà.');
        }

        $role = (new Role())
            ->setCode($normalizedCode)
            ->setLibelle(trim($input->libelle))
            ->setPerimetre($input->perimetre)
            ->setCreatedAt(new \DateTimeImmutable());

        $this->eM->persist($role);

        try {
            $this->eM->flush();
        } catch (UniqueConstraintViolationException) {
            throw new ConflictException('Ce code rôle existe déjà.');
        }

        return $role;
    }

    public function update(string $id, UpdateRoleInput $input): Role
    {
        $errors = $this->validator->validate($input);
        if (count($errors) > 0) {
            throw new ValidationFailedException($input, $errors);
        }

        $role = $this->getById($id);
        $normalizedCode = strtoupper(trim($input->code));
        $normalizedPerimetre = PersonnelRole::normalizePerimetre($input->perimetre);

        if ($this->isSystemRole($role) && $role->getCode() !== $normalizedCode) {
            throw new ConflictException('Le code d\'un rôle système ne peut pas être modifié.');
        }

        if ($this->isSystemRole($role) && $role->getPerimetre() !== $normalizedPerimetre) {
            throw new ConflictException('Le périmètre d\'un rôle système ne peut pas être modifié.');
        }

        if (!$role->getPersonnelRoles()->isEmpty() && $role->getPerimetre() !== $normalizedPerimetre) {
            throw new ConflictException('Le périmètre ne peut pas être modifié tant que le rôle est affecté à du personnel.');
        }

        $existing = $this->roleRepository->findOneBy(['code' => $normalizedCode]);
        if (null !== $existing && $existing->getId()?->toRfc4122() !== $role->getId()?->toRfc4122()) {
            throw new ConflictException('Ce code rôle existe déjà.');
        }

        $role
            ->setCode($normalizedCode)
            ->setLibelle(trim($input->libelle))
            ->setPerimetre($normalizedPerimetre);

        try {
            $this->eM->flush();
        } catch (UniqueConstraintViolationException) {
            throw new ConflictException('Ce code rôle existe déjà.');
        }

        return $role;
    }

    /**
     * @return array{
     *     role: array<string, mixed>,
     *     permissions: list<array<string, mixed>>,
     *     assignedPermissionIds: list<string>
     * }
     */
    public function getPermissionsMatrix(string $id): array
    {
        $role = $this->getById($id);
        $assignedIds = [];

        foreach ($role->getPermissions() as $permission) {
            $permissionId = $permission->getId()?->toRfc4122();
            if (null !== $permissionId) {
                $assignedIds[$permissionId] = true;
            }
        }

        $permissions = [];
        foreach ($this->permissionRepository->findBy([], ['module' => 'ASC', 'code' => 'ASC']) as $permission) {
            $permissionId = $permission->getId()?->toRfc4122();
            $permissions[] = [
                'id' => $permissionId,
                'code' => $permission->getCode(),
                'libelle' => $permission->getLibelle(),
                'description' => $permission->getDescription(),
                'module' => $permission->getModule(),
                'assigned' => null !== $permissionId && isset($assignedIds[$permissionId]),
            ];
        }

        return [
            'role' => [
                'id' => $role->getId()?->toRfc4122(),
                'code' => $role->getCode(),
                'libelle' => $role->getLibelle(),
                'perimetre' => $role->getPerimetre(),
            ],
            'permissions' => $permissions,
            'assignedPermissionIds' => array_keys($assignedIds),
        ];
    }

    public function syncPermissions(string $id, AssignRolePermissionsInput $input): Role
    {
        $errors = $this->validator->validate($input);
        if (count($errors) > 0) {
            throw new ValidationFailedException($input, $errors);
        }

        $role = $this->getById($id);
        $targetPermissions = [];
        $seenIds = [];

        foreach ($input->permissionIds as $permissionId) {
            if (!is_string($permissionId) || '' === trim($permissionId)) {
                throw new NotFoundException('Identifiant de permission invalide.');
            }

            if (!Uuid::isValid($permissionId)) {
                throw new NotFoundException('Permission non trouvée.');
            }

            if (isset($seenIds[$permissionId])) {
                continue;
            }

            $permission = $this->permissionRepository->find(Uuid::fromString($permissionId));
            if (null === $permission) {
                throw new NotFoundException('Permission non trouvée.');
            }

            $seenIds[$permissionId] = true;
            $targetPermissions[] = $permission;
        }

        $targetIds = array_flip(array_map(
            static fn (Permission $permission): string => $permission->getId()?->toRfc4122() ?? '',
            $targetPermissions,
        ));

        foreach ($role->getPermissions()->toArray() as $currentPermission) {
            $currentId = $currentPermission->getId()?->toRfc4122();
            if (null === $currentId || !isset($targetIds[$currentId])) {
                $role->removePermission($currentPermission);
            }
        }

        foreach ($targetPermissions as $permission) {
            $role->addPermission($permission);
        }

        $this->eM->flush();

        return $role;
    }

    public function paginateAssignments(RolePermissionListQuery $query): PaginatedResult
    {
        $errors = $this->validator->validate($query);
        if (count($errors) > 0) {
            throw new ValidationFailedException($query, $errors);
        }

        $result = $this->roleRepository->paginateAssignments(
            $query->page,
            $query->limit,
            $query->search,
            $query->module,
        );

        return new PaginatedResult(
            array_map([$this, 'serializeAssignment'], $result['items']),
            $query->page,
            $query->limit,
            $result['total'],
        );
    }

    public function createAssignment(CreateRolePermissionAssignmentInput $input): array
    {
        $errors = $this->validator->validate($input);
        if (count($errors) > 0) {
            throw new ValidationFailedException($input, $errors);
        }

        $this->syncPermissions($input->roleId, new AssignRolePermissionsInput($input->permissionIds));

        return $this->getPermissionsMatrix($input->roleId);
    }

    public function clearPermissions(string $id): void
    {
        $role = $this->getById($id);

        foreach ($role->getPermissions()->toArray() as $permission) {
            $role->removePermission($permission);
        }

        $this->eM->flush();
    }

    public function removePermission(string $roleId, string $permissionId): array
    {
        $role = $this->getById($roleId);

        if (!Uuid::isValid($permissionId)) {
            throw new NotFoundException('Permission non trouvée.');
        }

        $permission = $this->permissionRepository->find(Uuid::fromString($permissionId));
        if (null === $permission) {
            throw new NotFoundException('Permission non trouvée.');
        }

        $role->removePermission($permission);
        $this->eM->flush();

        return $this->getPermissionsMatrix($roleId);
    }

    /**
     * @return array<string, mixed>
     */
    public function serializeAssignment(Role $role): array
    {
        $permissions = [];
        foreach ($role->getPermissions() as $permission) {
            $permissions[] = [
                'id' => $permission->getId()?->toRfc4122(),
                'code' => $permission->getCode(),
                'libelle' => $permission->getLibelle(),
                'module' => $permission->getModule(),
            ];
        }

        usort($permissions, static fn (array $a, array $b): int => [$a['module'], $a['code']] <=> [$b['module'], $b['code']]);

        return [
            'id' => $role->getId()?->toRfc4122(),
            'role' => [
                'id' => $role->getId()?->toRfc4122(),
                'code' => $role->getCode(),
                'libelle' => $role->getLibelle(),
                'perimetre' => $role->getPerimetre(),
                'system' => in_array($role->getCode(), self::SYSTEM_ROLE_CODES, true),
            ],
            'permissionsCount' => count($permissions),
            'permissions' => $permissions,
        ];
    }

    public function delete(string $id): void
    {
        $role = $this->getById($id);

        if ($this->isSystemRole($role)) {
            throw new ConflictException('Un rôle système ne peut pas être supprimé.');
        }

        if (!$role->getPersonnelRoles()->isEmpty()) {
            throw new ConflictException('Ce rôle est encore affecté à du personnel et ne peut pas être supprimé.');
        }

        $this->eM->remove($role);
        $this->eM->flush();
    }

    private function isSystemRole(Role $role): bool
    {
        return in_array($role->getCode(), self::SYSTEM_ROLE_CODES, true);
    }
}

