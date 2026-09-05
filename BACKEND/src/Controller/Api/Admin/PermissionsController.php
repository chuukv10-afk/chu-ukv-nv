<?php

namespace App\Controller\Api\Admin;

use App\Controller\Api\Trait\JsonResponseTrait;
use App\DTO\Admin\CreatePermissionInput;
use App\DTO\Admin\PermissionListQuery;
use App\DTO\Admin\UpdatePermissionInput;
use App\Entity\Permission;
use App\Security\Permission\AdminPermissions;
use App\Service\Permission\PermissionService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryString;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/v1/admin/permissions')]
#[IsGranted('ROLE_PERSONNEL')]
final class PermissionsController extends AbstractController
{
    use JsonResponseTrait;

    public function __construct(
        private readonly PermissionService $permissionService,
    ) {
    }

    #[Route('', name: 'api_admin_permissions_index', methods: ['GET'])]
    #[IsGranted(AdminPermissions::PERMISSION_READ)]
    public function index(#[MapQueryString] PermissionListQuery $query = new PermissionListQuery()): JsonResponse
    {
        $result = $this->permissionService->paginate($query);

        return $this->apiPaginatedSuccess(
            array_map([$this, 'serialize'], $result->items),
            $result->page,
            $result->limit,
            $result->total,
            'Liste des permissions récupérée avec succès.',
        );
    }

    #[Route('/modules', name: 'api_admin_permissions_modules', methods: ['GET'])]
    #[IsGranted(AdminPermissions::PERMISSION_READ)]
    public function modules(): JsonResponse
    {
        return $this->apiSuccess(
            Permission::getModules(),
            'Modules de permissions récupérés avec succès.',
        );
    }

    #[Route('/{id}', name: 'api_admin_permissions_show', methods: ['GET'])]
    #[IsGranted(AdminPermissions::PERMISSION_READ)]
    public function show(string $id): JsonResponse
    {
        return $this->apiSuccess(
            $this->serialize($this->permissionService->getById($id)),
            'Permission récupérée avec succès.',
        );
    }

    #[Route('', name: 'api_admin_permissions_create', methods: ['POST'])]
    #[IsGranted(AdminPermissions::PERMISSION_CREATE)]
    public function create(#[MapRequestPayload] CreatePermissionInput $input): JsonResponse
    {
        return $this->apiSuccess(
            $this->serialize($this->permissionService->create($input)),
            'Permission créée avec succès.',
            Response::HTTP_CREATED,
        );
    }

    #[Route('/{id}', name: 'api_admin_permissions_update', methods: ['PUT'])]
    #[IsGranted(AdminPermissions::PERMISSION_UPDATE)]
    public function update(string $id, #[MapRequestPayload] UpdatePermissionInput $input): JsonResponse
    {
        return $this->apiSuccess(
            $this->serialize($this->permissionService->update($id, $input)),
            'Permission mise à jour avec succès.',
        );
    }

    #[Route('/{id}', name: 'api_admin_permissions_delete', methods: ['DELETE'])]
    #[IsGranted(AdminPermissions::PERMISSION_DELETE)]
    public function delete(string $id): JsonResponse
    {
        $this->permissionService->delete($id);

        return $this->apiSuccess(message: 'Permission supprimée avec succès.');
    }

    private function serialize(Permission $permission): array
    {
        return [
            'id' => $permission->getId()?->toRfc4122(),
            'code' => $permission->getCode(),
            'libelle' => $permission->getLibelle(),
            'description' => $permission->getDescription(),
            'module' => $permission->getModule(),
            'rolesCount' => $permission->getRoles()->count(),
            'createdAt' => $permission->getCreatedAt()?->format(\DateTimeInterface::ATOM),
        ];
    }
}
