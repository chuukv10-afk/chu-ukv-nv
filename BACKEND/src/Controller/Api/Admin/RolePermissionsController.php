<?php

namespace App\Controller\Api\Admin;

use App\Controller\Api\Trait\JsonResponseTrait;
use App\DTO\Admin\AssignRolePermissionsInput;
use App\DTO\Admin\CreateRolePermissionAssignmentInput;
use App\DTO\Admin\RolePermissionListQuery;
use App\Security\Permission\AdminPermissions;
use App\Service\Role\RoleService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryString;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/v1/admin/role-permissions')]
#[IsGranted('ROLE_PERSONNEL')]
final class RolePermissionsController extends AbstractController
{
    use JsonResponseTrait;

    public function __construct(
        private readonly RoleService $roleService,
    ) {
    }

    #[Route('', name: 'api_admin_role_permissions_index', methods: ['GET'])]
    #[IsGranted(AdminPermissions::ROLE_PERMISSION_READ)]
    public function index(#[MapQueryString] RolePermissionListQuery $query = new RolePermissionListQuery()): JsonResponse
    {
        $result = $this->roleService->paginateAssignments($query);

        return $this->apiPaginatedSuccess(
            $result->items,
            $result->page,
            $result->limit,
            $result->total,
            'Liste des affectations permissions/rôles récupérée avec succès.',
        );
    }

    #[Route('/{roleId}', name: 'api_admin_role_permissions_show', methods: ['GET'])]
    #[IsGranted(AdminPermissions::ROLE_PERMISSION_READ)]
    public function show(string $roleId): JsonResponse
    {
        return $this->apiSuccess(
            $this->roleService->getPermissionsMatrix($roleId),
            'Affectation permissions/rôle récupérée avec succès.',
        );
    }

    #[Route('', name: 'api_admin_role_permissions_create', methods: ['POST'])]
    #[IsGranted(AdminPermissions::ROLE_PERMISSION_ASSIGN)]
    public function create(#[MapRequestPayload] CreateRolePermissionAssignmentInput $input): JsonResponse
    {
        return $this->apiSuccess(
            $this->roleService->createAssignment($input),
            'Permissions affectées au rôle avec succès.',
            Response::HTTP_CREATED,
        );
    }

    #[Route('/{roleId}', name: 'api_admin_role_permissions_update', methods: ['PUT'])]
    #[IsGranted(AdminPermissions::ROLE_PERMISSION_ASSIGN)]
    public function update(string $roleId, #[MapRequestPayload] AssignRolePermissionsInput $input): JsonResponse
    {
        $this->roleService->syncPermissions($roleId, $input);

        return $this->apiSuccess(
            $this->roleService->getPermissionsMatrix($roleId),
            'Affectation permissions/rôle mise à jour avec succès.',
        );
    }

    #[Route('/{roleId}', name: 'api_admin_role_permissions_clear', methods: ['DELETE'])]
    #[IsGranted(AdminPermissions::ROLE_PERMISSION_DELETE)]
    public function clear(string $roleId): JsonResponse
    {
        $this->roleService->clearPermissions($roleId);

        return $this->apiSuccess(message: 'Toutes les permissions ont été retirées du rôle.');
    }

    #[Route('/{roleId}/permissions/{permissionId}', name: 'api_admin_role_permissions_remove', methods: ['DELETE'])]
    #[IsGranted(AdminPermissions::ROLE_PERMISSION_DELETE)]
    public function remove(string $roleId, string $permissionId): JsonResponse
    {
        return $this->apiSuccess(
            $this->roleService->removePermission($roleId, $permissionId),
            'Permission retirée du rôle avec succès.',
        );
    }
}
