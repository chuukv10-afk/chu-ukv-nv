<?php

namespace App\Controller\Api;

use App\Controller\Api\Trait\JsonResponseTrait;
use App\DTO\Storage\ConfirmStoredFileInput;
use App\DTO\Storage\PrepareStoredFileInput;
use App\Entity\Personnel;
use App\Exception\ConflictException;
use App\Security\Permission\AdminPermissions;
use App\Service\Personnel\PersonnelAvatarService;
use App\Service\Storage\StoredFileResponse;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/v1/me/avatar')]
#[IsGranted('ROLE_PERSONNEL')]
final class MeAvatarController extends AbstractController
{
    use JsonResponseTrait;

    public function __construct(
        private readonly PersonnelAvatarService $avatarService,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    #[Route('', name: 'api_me_avatar_show', methods: ['GET'])]
    public function show(#[CurrentUser] ?Personnel $personnel): Response
    {
        $personnel = $this->requirePersonnel($personnel);
        $file = $this->avatarService->read($personnel);
        if (null === $file) {
            throw new NotFoundHttpException('Aucune photo de profil n\'est liée à ce compte.');
        }

        return StoredFileResponse::create($file);
    }

    #[Route('/prepare', name: 'api_me_avatar_prepare', methods: ['POST'])]
    #[IsGranted(AdminPermissions::PROFIL_PHOTO_UPDATE)]
    public function prepare(#[MapRequestPayload] PrepareStoredFileInput $input, #[CurrentUser] ?Personnel $personnel): JsonResponse
    {
        $personnel = $this->requirePersonnel($personnel);

        return $this->apiSuccess(
            $this->avatarService->prepareDirectUpload($personnel, $input->mimeType, $input->size),
            'Envoi de la photo préparé.',
        );
    }

    #[Route('/confirm', name: 'api_me_avatar_confirm', methods: ['POST'])]
    #[IsGranted(AdminPermissions::PROFIL_PHOTO_UPDATE)]
    public function confirm(#[MapRequestPayload] ConfirmStoredFileInput $input, #[CurrentUser] ?Personnel $personnel): JsonResponse
    {
        $personnel = $this->requirePersonnel($personnel);
        $this->avatarService->confirmDirectUpload($personnel, $input->filename);
        $this->entityManager->flush();

        return $this->apiSuccess(
            ['avatarUrl' => $this->avatarService->buildAvatarUrl($personnel)],
            'Photo de profil enregistrée avec succès.',
        );
    }

    #[Route('', name: 'api_me_avatar_upload', methods: ['POST'])]
    #[IsGranted(AdminPermissions::PROFIL_PHOTO_UPDATE)]
    public function upload(Request $request, #[CurrentUser] ?Personnel $personnel): JsonResponse
    {
        $personnel = $this->requirePersonnel($personnel);

        /** @var UploadedFile|null $file */
        $file = $request->files->get('avatar');
        if (!$file instanceof UploadedFile) {
            throw new BadRequestHttpException('Aucun fichier photo fourni.');
        }

        $this->avatarService->upload($personnel, $file);

        try {
            $this->entityManager->flush();
        } catch (\Throwable) {
            $this->avatarService->delete($personnel);
            throw new ConflictException('Impossible d\'enregistrer la photo de profil.');
        }

        return $this->apiSuccess(
            ['avatarUrl' => $this->avatarService->buildAvatarUrl($personnel)],
            'Photo de profil enregistrée avec succès.',
        );
    }

    #[Route('', name: 'api_me_avatar_delete', methods: ['DELETE'])]
    #[IsGranted(AdminPermissions::PROFIL_PHOTO_UPDATE)]
    public function delete(#[CurrentUser] ?Personnel $personnel): JsonResponse
    {
        $personnel = $this->requirePersonnel($personnel);
        $this->avatarService->delete($personnel);
        $this->entityManager->flush();

        return $this->apiSuccess(
            ['avatarUrl' => null],
            'Photo de profil supprimée avec succès.',
        );
    }

    private function requirePersonnel(?Personnel $personnel): Personnel
    {
        if (!$personnel instanceof Personnel) {
            throw $this->createAccessDeniedException('Non authentifié.');
        }

        return $personnel;
    }
}
