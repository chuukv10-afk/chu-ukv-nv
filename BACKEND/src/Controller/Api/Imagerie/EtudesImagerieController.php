<?php

namespace App\Controller\Api\Imagerie;

use App\Controller\Api\Trait\JsonResponseTrait;
use App\DTO\Imagerie\ConfirmImagerieImageInput;
use App\DTO\Imagerie\CreateEtudeImagerieInput;
use App\DTO\Imagerie\ImagerieListQuery;
use App\DTO\Imagerie\InterpretEtudeImagerieInput;
use App\DTO\Storage\PrepareStoredFileInput;
use App\Security\Permission\CliniquePermissions;
use App\Service\Imagerie\ImageriePdfService;
use App\Service\Imagerie\ImagerieService;
use App\Service\Storage\StoredFileResponse;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryString;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/v1/imagerie/etudes')]
#[IsGranted('ROLE_PERSONNEL')]
final class EtudesImagerieController extends AbstractController
{
    use JsonResponseTrait;

    public function __construct(
        private readonly ImagerieService $imagerieService,
        private readonly ImageriePdfService $imageriePdfService,
    ) {
    }

    #[Route('', name: 'api_imagerie_etudes_index', methods: ['GET'])]
    #[IsGranted(CliniquePermissions::IMAGERIE_READ)]
    public function index(#[MapQueryString] ImagerieListQuery $query = new ImagerieListQuery()): JsonResponse
    {
        $result = $this->imagerieService->paginate($query);

        return $this->apiPaginatedSuccess($result->items, $result->page, $result->limit, $result->total, 'Journal d\'imagerie récupéré.');
    }

    #[Route('', name: 'api_imagerie_etudes_create', methods: ['POST'])]
    #[IsGranted(CliniquePermissions::IMAGERIE_CREATE)]
    public function create(#[MapRequestPayload] CreateEtudeImagerieInput $input): JsonResponse
    {
        return $this->apiSuccess(
            $this->imagerieService->serializeDetail($this->imagerieService->create($input)),
            'Étude d\'imagerie créée.',
            Response::HTTP_CREATED,
        );
    }

    #[Route('/medecins', name: 'api_imagerie_etudes_medecins', methods: ['GET'])]
    public function medecins(Request $request): JsonResponse
    {
        if (
            !$this->isGranted(CliniquePermissions::IMAGERIE_READ)
            && !$this->isGranted(CliniquePermissions::IMAGERIE_CREATE)
            && !$this->isGranted(CliniquePermissions::IMAGERIE_UPDATE)
        ) {
            throw $this->createAccessDeniedException();
        }

        return $this->apiSuccess(
            $this->imagerieService->listMedecins($request->query->get('search')),
            'Médecins récupérés.',
        );
    }

    #[Route('/{id}', name: 'api_imagerie_etudes_show', methods: ['GET'], requirements: ['id' => '\d+'])]
    #[IsGranted(CliniquePermissions::IMAGERIE_READ)]
    public function show(int $id): JsonResponse
    {
        return $this->apiSuccess($this->imagerieService->serializeDetail($this->imagerieService->getById($id)), 'Étude récupérée.');
    }

    #[Route('/{id}/interpreter', name: 'api_imagerie_etudes_interpret', methods: ['POST'], requirements: ['id' => '\d+'])]
    #[IsGranted(CliniquePermissions::IMAGERIE_INTERPRET)]
    public function interpret(int $id, #[MapRequestPayload] InterpretEtudeImagerieInput $input): JsonResponse
    {
        return $this->apiSuccess(
            $this->imagerieService->serializeDetail($this->imagerieService->interpret($id, $input)),
            'Compte-rendu enregistré.',
        );
    }

    #[Route('/{id}/valider', name: 'api_imagerie_etudes_validate', methods: ['POST'], requirements: ['id' => '\d+'])]
    #[IsGranted(CliniquePermissions::IMAGERIE_VALIDATE)]
    public function validate(int $id): JsonResponse
    {
        return $this->apiSuccess(
            $this->imagerieService->serializeDetail($this->imagerieService->validate($id)),
            'Compte-rendu validé.',
        );
    }

    #[Route('/{id}/annuler', name: 'api_imagerie_etudes_cancel', methods: ['POST'], requirements: ['id' => '\d+'])]
    #[IsGranted(CliniquePermissions::IMAGERIE_UPDATE)]
    public function cancel(int $id): JsonResponse
    {
        return $this->apiSuccess(
            $this->imagerieService->serializeDetail($this->imagerieService->cancel($id)),
            'Étude annulée.',
        );
    }

    #[Route('/{id}', name: 'api_imagerie_etudes_delete', methods: ['DELETE'], requirements: ['id' => '\d+'])]
    #[IsGranted(CliniquePermissions::IMAGERIE_DELETE)]
    public function delete(int $id): JsonResponse
    {
        $this->imagerieService->delete($id);

        return $this->apiSuccess(message: 'Étude supprimée.');
    }

    #[Route('/{id}/pdf', name: 'api_imagerie_etudes_pdf', methods: ['GET'], requirements: ['id' => '\d+'])]
    #[IsGranted(CliniquePermissions::IMAGERIE_EXPORT)]
    public function pdf(int $id): Response
    {
        return $this->imageriePdfService->createResponse($this->imagerieService->getById($id));
    }

    #[Route('/{id}/bon-pdf', name: 'api_imagerie_etudes_bon_pdf', methods: ['GET'], requirements: ['id' => '\d+'])]
    #[IsGranted(CliniquePermissions::IMAGERIE_EXPORT)]
    public function bonPdf(int $id): Response
    {
        return $this->imageriePdfService->createBonDemandeResponse($this->imagerieService->getById($id));
    }

    #[Route('/{id}/images/prepare', name: 'api_imagerie_images_prepare', methods: ['POST'], requirements: ['id' => '\d+'])]
    #[IsGranted(CliniquePermissions::IMAGERIE_UPLOAD)]
    public function prepareImage(int $id, #[MapRequestPayload] PrepareStoredFileInput $input): JsonResponse
    {
        return $this->apiSuccess($this->imagerieService->prepareImage($id, $input), 'Envoi préparé.');
    }

    #[Route('/{id}/images/confirm', name: 'api_imagerie_images_confirm', methods: ['POST'], requirements: ['id' => '\d+'])]
    #[IsGranted(CliniquePermissions::IMAGERIE_UPLOAD)]
    public function confirmImage(int $id, #[MapRequestPayload] ConfirmImagerieImageInput $input): JsonResponse
    {
        return $this->apiSuccess(
            $this->imagerieService->serializeDetail($this->imagerieService->confirmImage($id, $input)),
            'Image enregistrée.',
        );
    }

    #[Route('/{id}/images', name: 'api_imagerie_images_upload', methods: ['POST'], requirements: ['id' => '\d+'])]
    #[IsGranted(CliniquePermissions::IMAGERIE_UPLOAD)]
    public function uploadImage(int $id, Request $request): JsonResponse
    {
        $file = $request->files->get('image');
        if (!$file instanceof UploadedFile) {
            throw new BadRequestHttpException('Aucun fichier fourni.');
        }
        $contents = file_get_contents($file->getPathname());
        if (false === $contents) {
            throw new BadRequestHttpException('Fichier illisible.');
        }

        $originalName = $file->getClientOriginalName() ?: 'image';

        return $this->apiSuccess(
            $this->imagerieService->serializeDetail($this->imagerieService->uploadLocal(
                $id,
                (string) ($file->getMimeType() ?? ''),
                (int) $file->getSize(),
                $originalName,
                $contents,
            )),
            str_ends_with(strtolower($originalName), '.pdf') ? 'PDF enregistré.' : 'Image enregistrée.',
        );
    }

    #[Route('/{id}/images/{imageId}', name: 'api_imagerie_images_show', methods: ['GET'], requirements: ['id' => '\d+', 'imageId' => '\d+'])]
    #[IsGranted(CliniquePermissions::IMAGERIE_READ)]
    public function showImage(int $id, int $imageId): Response
    {
        return StoredFileResponse::create($this->imagerieService->readImage($id, $imageId));
    }

    #[Route('/{id}/images/{imageId}', name: 'api_imagerie_images_delete', methods: ['DELETE'], requirements: ['id' => '\d+', 'imageId' => '\d+'])]
    #[IsGranted(CliniquePermissions::IMAGERIE_UPLOAD)]
    public function deleteImage(int $id, int $imageId): JsonResponse
    {
        return $this->apiSuccess(
            $this->imagerieService->serializeDetail($this->imagerieService->deleteImage($id, $imageId)),
            'Image supprimée.',
        );
    }
}
