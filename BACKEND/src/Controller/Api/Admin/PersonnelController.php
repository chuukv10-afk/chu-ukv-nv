<?php

namespace App\Controller\Api\Admin;

use App\Controller\Api\Trait\JsonResponseTrait;
use App\DTO\Admin\CreatePersonnelInput;
use App\DTO\Admin\PersonnelListQuery;
use App\DTO\Admin\UpdatePersonnelInput;
use App\DTO\Storage\ConfirmStoredFileInput;
use App\DTO\Storage\PrepareStoredFileInput;
use App\Entity\Personnel;
use App\Security\Permission\AdminPermissions;
use App\Security\Permission\RhPermissions;
use App\Service\Personnel\PersonnelService;
use App\Service\Personnel\PersonnelExportService;
use App\Service\Storage\StoredFileResponse;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryString;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/v1/admin/personnels')]
#[IsGranted('ROLE_PERSONNEL')]
final class PersonnelController extends AbstractController
{
    use JsonResponseTrait;

    public function __construct(
        private readonly PersonnelService $personnelService,
        private readonly PersonnelExportService $personnelExportService,
    ) {
    }

    #[Route('', name: 'api_admin_personnels_index', methods: ['GET'])]
    #[IsGranted(AdminPermissions::PERSONNEL_READ)]
    public function index(#[MapQueryString] PersonnelListQuery $query = new PersonnelListQuery()): JsonResponse
    {
        $result = $this->personnelService->paginate($query);

        return $this->apiPaginatedSuccess(
            $result->items,
            $result->page,
            $result->limit,
            $result->total,
            'Liste du personnel récupérée avec succès.',
        );
    }

    #[Route('/meta', name: 'api_admin_personnels_meta', methods: ['GET'])]
    #[IsGranted(AdminPermissions::PERSONNEL_READ)]
    public function meta(): JsonResponse
    {
        return $this->apiSuccess(
            [
                'statuses' => Personnel::getStatuses(),
                'types' => Personnel::getTypes(),
            ],
            'Métadonnées personnel récupérées avec succès.',
        );
    }

    #[Route('/export', name: 'api_admin_personnels_export', methods: ['GET'])]
    #[IsGranted(AdminPermissions::PERSONNEL_EXPORT)]
    public function export(
        Request $request,
        #[MapQueryString] PersonnelListQuery $query = new PersonnelListQuery(),
    ): Response {
        $format = strtolower(trim((string) $request->query->get('format', 'xlsx')));

        if (!in_array($format, ['pdf', 'xlsx'], true)) {
            throw new BadRequestHttpException('Format d\'export invalide. Utilisez pdf ou xlsx.');
        }

        $rows = $this->personnelService->buildExportRows($query);

        return match ($format) {
            'pdf' => $this->personnelExportService->createPdfResponse($rows),
            default => $this->personnelExportService->createExcelResponse($rows),
        };
    }

    #[Route('/{id}/avatar', name: 'api_admin_personnels_avatar_show', methods: ['GET'])]
    public function showAvatar(string $id, #[CurrentUser] ?Personnel $viewer): Response
    {
        $personnel = $this->personnelService->getById($id);
        $this->assertCanViewAvatar($personnel, $viewer);

        $file = $this->personnelService->readAvatar($id);
        if (null === $file) {
            throw new NotFoundHttpException('Avatar non trouvé.');
        }

        return StoredFileResponse::create($file);
    }

    #[Route('/{id}/avatar/prepare', name: 'api_admin_personnels_avatar_prepare', methods: ['POST'])]
    #[IsGranted(AdminPermissions::PERSONNEL_UPDATE)]
    public function prepareAvatar(string $id, #[MapRequestPayload] PrepareStoredFileInput $input): JsonResponse
    {
        $personnel = $this->personnelService->getById($id);
        $this->denyAccessUnlessGranted(AdminPermissions::PERSONNEL_UPDATE, $personnel);

        return $this->apiSuccess(
            $this->personnelService->prepareAvatarUpload($id, $input->mimeType, $input->size),
            'Envoi de l\'avatar préparé.',
        );
    }

    #[Route('/{id}/avatar/confirm', name: 'api_admin_personnels_avatar_confirm', methods: ['POST'])]
    #[IsGranted(AdminPermissions::PERSONNEL_UPDATE)]
    public function confirmAvatar(string $id, #[MapRequestPayload] ConfirmStoredFileInput $input): JsonResponse
    {
        $personnel = $this->personnelService->getById($id);
        $this->denyAccessUnlessGranted(AdminPermissions::PERSONNEL_UPDATE, $personnel);

        return $this->apiSuccess(
            $this->personnelService->serialize($this->personnelService->confirmAvatarUpload($id, $input->filename)),
            'Avatar mis à jour avec succès.',
        );
    }

    #[Route('/{id}/avatar', name: 'api_admin_personnels_avatar_upload', methods: ['POST'])]
    #[IsGranted(AdminPermissions::PERSONNEL_UPDATE)]
    public function uploadAvatar(string $id, Request $request): JsonResponse
    {
        $personnel = $this->personnelService->getById($id);
        $this->denyAccessUnlessGranted(AdminPermissions::PERSONNEL_UPDATE, $personnel);

        /** @var UploadedFile|null $file */
        $file = $request->files->get('avatar');
        if (!$file instanceof UploadedFile) {
            throw new BadRequestHttpException('Aucun fichier avatar fourni.');
        }

        return $this->apiSuccess(
            $this->personnelService->serialize($this->personnelService->uploadAvatar($id, $file)),
            'Avatar mis à jour avec succès.',
        );
    }

    #[Route('/{id}/avatar', name: 'api_admin_personnels_avatar_delete', methods: ['DELETE'])]
    #[IsGranted(AdminPermissions::PERSONNEL_UPDATE)]
    public function deleteAvatar(string $id): JsonResponse
    {
        $personnel = $this->personnelService->getById($id);
        $this->denyAccessUnlessGranted(AdminPermissions::PERSONNEL_UPDATE, $personnel);

        return $this->apiSuccess(
            $this->personnelService->serialize($this->personnelService->deleteAvatar($id)),
            'Avatar supprimé avec succès.',
        );
    }

    #[Route('/{id}/signature', name: 'api_admin_personnels_signature_show', methods: ['GET'])]
    public function showSignature(string $id, #[CurrentUser] ?Personnel $viewer): Response
    {
        $personnel = $this->personnelService->getById($id);
        $this->assertCanViewAvatar($personnel, $viewer);

        $file = $this->personnelService->readSignature($id);
        if (null === $file) {
            throw new NotFoundHttpException('Signature non trouvée.');
        }

        return StoredFileResponse::create($file);
    }

    #[Route('/{id}/signature/prepare', name: 'api_admin_personnels_signature_prepare', methods: ['POST'])]
    #[IsGranted(AdminPermissions::PERSONNEL_UPDATE)]
    public function prepareSignature(string $id, #[MapRequestPayload] PrepareStoredFileInput $input): JsonResponse
    {
        $personnel = $this->personnelService->getById($id);
        $this->denyAccessUnlessGranted(AdminPermissions::PERSONNEL_UPDATE, $personnel);

        return $this->apiSuccess(
            $this->personnelService->prepareSignatureUpload($id, $input->mimeType, $input->size),
            'Envoi de la signature préparé.',
        );
    }

    #[Route('/{id}/signature/confirm', name: 'api_admin_personnels_signature_confirm', methods: ['POST'])]
    #[IsGranted(AdminPermissions::PERSONNEL_UPDATE)]
    public function confirmSignature(string $id, #[MapRequestPayload] ConfirmStoredFileInput $input): JsonResponse
    {
        $personnel = $this->personnelService->getById($id);
        $this->denyAccessUnlessGranted(AdminPermissions::PERSONNEL_UPDATE, $personnel);

        return $this->apiSuccess(
            $this->personnelService->serialize($this->personnelService->confirmSignatureUpload($id, $input->filename)),
            'Signature mise à jour avec succès.',
        );
    }

    #[Route('/{id}/signature', name: 'api_admin_personnels_signature_upload', methods: ['POST'])]
    #[IsGranted(AdminPermissions::PERSONNEL_UPDATE)]
    public function uploadSignature(string $id, Request $request): JsonResponse
    {
        $personnel = $this->personnelService->getById($id);
        $this->denyAccessUnlessGranted(AdminPermissions::PERSONNEL_UPDATE, $personnel);

        /** @var UploadedFile|null $file */
        $file = $request->files->get('signature');
        if (!$file instanceof UploadedFile) {
            throw new BadRequestHttpException('Aucun fichier signature fourni.');
        }

        return $this->apiSuccess(
            $this->personnelService->serialize($this->personnelService->uploadSignature($id, $file)),
            'Signature mise à jour avec succès.',
        );
    }

    #[Route('/{id}/signature', name: 'api_admin_personnels_signature_delete', methods: ['DELETE'])]
    #[IsGranted(AdminPermissions::PERSONNEL_UPDATE)]
    public function deleteSignature(string $id): JsonResponse
    {
        $personnel = $this->personnelService->getById($id);
        $this->denyAccessUnlessGranted(AdminPermissions::PERSONNEL_UPDATE, $personnel);

        return $this->apiSuccess(
            $this->personnelService->serialize($this->personnelService->deleteSignature($id)),
            'Signature supprimée avec succès.',
        );
    }

    #[Route('/{id}', name: 'api_admin_personnels_show', methods: ['GET'])]
    #[IsGranted(AdminPermissions::PERSONNEL_READ)]
    public function show(string $id): JsonResponse
    {
        $personnel = $this->personnelService->getById($id);
        $this->denyAccessUnlessGranted(AdminPermissions::PERSONNEL_READ, $personnel);

        return $this->apiSuccess(
            $this->personnelService->serialize($personnel),
            'Personnel récupéré avec succès.',
        );
    }

    #[Route('', name: 'api_admin_personnels_create', methods: ['POST'])]
    #[IsGranted(AdminPermissions::PERSONNEL_CREATE)]
    public function create(#[MapRequestPayload] CreatePersonnelInput $input): JsonResponse
    {
        return $this->apiSuccess(
            $this->personnelService->serialize($this->personnelService->create($input)),
            'Personnel créé avec succès.',
            Response::HTTP_CREATED,
        );
    }

    #[Route('/{id}', name: 'api_admin_personnels_update', methods: ['PUT'])]
    #[IsGranted(AdminPermissions::PERSONNEL_UPDATE)]
    public function update(string $id, #[MapRequestPayload] UpdatePersonnelInput $input): JsonResponse
    {
        return $this->apiSuccess(
            $this->personnelService->serialize($this->personnelService->update($id, $input)),
            'Personnel mis à jour avec succès.',
        );
    }

    #[Route('/{id}', name: 'api_admin_personnels_delete', methods: ['DELETE'])]
    #[IsGranted(AdminPermissions::PERSONNEL_DELETE)]
    public function delete(string $id): JsonResponse
    {
        $this->personnelService->delete($id);

        return $this->apiSuccess(message: 'Personnel supprimé avec succès.');
    }

    private function assertCanViewAvatar(Personnel $target, ?Personnel $viewer): void
    {
        if ($viewer instanceof Personnel && $viewer->getId()?->equals($target->getId())) {
            return;
        }

        if ($this->isGranted(AdminPermissions::PERSONNEL_READ, $target)
            || $this->isGranted(RhPermissions::PERSONNEL_READ, $target)
        ) {
            return;
        }

        throw $this->createAccessDeniedException('Accès refusé : vous n\'avez pas accès à ce fichier.');
    }
}
