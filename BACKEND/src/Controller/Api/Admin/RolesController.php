<?php

namespace App\Controller\Api\Admin;

use App\Controller\Api\Trait\JsonResponseTrait;
use App\DTO\Admin\AssignRolePermissionsInput;
use App\DTO\Admin\CreateRoleInput;
use App\DTO\Admin\UpdateRoleInput;
use App\Entity\PersonnelRole;
use App\Entity\Role;
use App\Security\Permission\AdminPermissions;
use App\Service\Role\RoleService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/v1/admin/roles')]
#[IsGranted('ROLE_PERSONNEL')]
final class RolesController extends AbstractController
{
    use JsonResponseTrait;

    public function __construct(
        private readonly RoleService $roleService,
    ) {
    }

    #[Route('', name: 'api_admin_roles_index', methods: ['GET'])]
    #[IsGranted(AdminPermissions::ROLE_READ)]
    public function index(): JsonResponse
    {
        return $this->apiSuccess(
            array_map([$this, 'serialize'], $this->roleService->findAll()),
            'Liste des rôles récupérée avec succès.',
        );
    }

    #[Route('/{id}/permissions', name: 'api_admin_roles_permissions_show', methods: ['GET'])]
    #[IsGranted(AdminPermissions::ROLE_PERMISSION_READ)]
    public function permissions(string $id): JsonResponse
    {
        $role = $this->roleService->getById($id);
        $this->denyAccessUnlessGranted(AdminPermissions::ROLE_PERMISSION_READ, $role);

        return $this->apiSuccess(
            $this->roleService->getPermissionsMatrix($id),
            'Permissions du rôle récupérées avec succès.',
        );
    }

    #[Route('/{id}/permissions', name: 'api_admin_roles_permissions_sync', methods: ['PUT'])]
    #[IsGranted(AdminPermissions::ROLE_PERMISSION_ASSIGN)]
    public function syncPermissions(string $id, #[MapRequestPayload] AssignRolePermissionsInput $input): JsonResponse
    {
        $role = $this->roleService->getById($id);
        $this->denyAccessUnlessGranted(AdminPermissions::ROLE_PERMISSION_ASSIGN, $role);

        $this->roleService->syncPermissions($id, $input);

        return $this->apiSuccess(
            $this->roleService->getPermissionsMatrix($id),
            'Permissions du rôle mises à jour avec succès.',
        );
    }

    #[Route('/{id}', name: 'api_admin_roles_show', methods: ['GET'])]
    #[IsGranted(AdminPermissions::ROLE_READ)]
    public function show(string $id): JsonResponse
    {
        $role = $this->roleService->getById($id);
        $this->denyAccessUnlessGranted(AdminPermissions::ROLE_READ, $role);

        return $this->apiSuccess(
            $this->serialize($role),
            'Rôle récupéré avec succès.',
        );
    }

    #[Route('', name: 'api_admin_roles_create', methods: ['POST'])]
    #[IsGranted(AdminPermissions::ROLE_CREATE)]
    public function create(#[MapRequestPayload] CreateRoleInput $input): JsonResponse
    {
        return $this->apiSuccess(
            $this->serialize($this->roleService->create($input)),
            'Rôle créé avec succès.',
            Response::HTTP_CREATED,
        );
    }

    #[Route('/{id}', name: 'api_admin_roles_update', methods: ['PUT'])]
    #[IsGranted(AdminPermissions::ROLE_UPDATE)]
    public function update(string $id, #[MapRequestPayload] UpdateRoleInput $input): JsonResponse
    {
        $role = $this->roleService->getById($id);
        $this->denyAccessUnlessGranted(AdminPermissions::ROLE_UPDATE, $role);

        return $this->apiSuccess(
            $this->serialize($this->roleService->update($id, $input)),
            'Rôle mis à jour avec succès.',
        );
    }

    #[Route('/{id}', name: 'api_admin_roles_delete', methods: ['DELETE'])]
    #[IsGranted(AdminPermissions::ROLE_DELETE)]
    public function delete(string $id): JsonResponse
    {
        $role = $this->roleService->getById($id);
        $this->denyAccessUnlessGranted(AdminPermissions::ROLE_DELETE, $role);

        $this->roleService->delete($id);

        return $this->apiSuccess(message: 'Rôle supprimé avec succès.');
    }

    private function serialize(Role $role): array
    {
        return [
            'id' => $role->getId()?->toRfc4122(),
            'code' => $role->getCode(),
            'libelle' => $role->getLibelle(),
            'perimetre' => $role->getPerimetre(),
            'requiresService' => PersonnelRole::PERIMETRE_SERVICE === $role->getPerimetre(),
            'requiresDepartement' => PersonnelRole::PERIMETRE_DEPARTEMENT === $role->getPerimetre(),
            'permissionsCount' => $role->getPermissions()->count(),
            'personnelCount' => $role->getPersonnelRoles()->count(),
            'system' => in_array($role->getCode(), [Role::CODE_ADMIN, Role::CODE_PERSONNEL], true),
            'createdAt' => $role->getCreatedAt()?->format(\DateTimeInterface::ATOM),
        ];
    }
}
