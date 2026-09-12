<?php

namespace App\Controller\Api;

use App\Controller\Api\Trait\JsonResponseTrait;
use App\Entity\Personnel;
use App\Exception\ConflictException;
use App\Security\Permission\AdminPermissions;
use App\Service\Personnel\PersonnelSignatureService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/v1/me/signature')]
#[IsGranted('ROLE_PERSONNEL')]
final class MeSignatureController extends AbstractController
{
    use JsonResponseTrait;

    public function __construct(
        private readonly PersonnelSignatureService $signatureService,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    #[Route('', name: 'api_me_signature_show', methods: ['GET'])]
    public function show(#[CurrentUser] ?Personnel $personnel): Response
    {
        $personnel = $this->requirePersonnel($personnel);
        $path = $this->signatureService->resolvePath($personnel);
        if (null === $path) {
            throw new NotFoundHttpException('Aucune signature n\'est liée à ce compte.');
        }

        $response = new BinaryFileResponse($path);
        $mimeType = $this->signatureService->resolveMimeType($personnel);
        if (null !== $mimeType) {
            $response->headers->set('Content-Type', $mimeType);
        }
        $response->setPrivate();
        $response->headers->addCacheControlDirective('no-store');

        return $response;
    }

    #[Route('', name: 'api_me_signature_upload', methods: ['POST'])]
    #[IsGranted(AdminPermissions::SIGNATURE_UPDATE)]
    public function upload(Request $request, #[CurrentUser] ?Personnel $personnel): JsonResponse
    {
        $personnel = $this->requirePersonnel($personnel);

        /** @var UploadedFile|null $file */
        $file = $request->files->get('signature');
        if (!$file instanceof UploadedFile) {
            throw new BadRequestHttpException('Aucun fichier signature fourni.');
        }

        $this->signatureService->upload($personnel, $file);

        try {
            $this->entityManager->flush();
        } catch (\Throwable) {
            $this->signatureService->delete($personnel);
            throw new ConflictException('Impossible d\'enregistrer la signature.');
        }

        return $this->apiSuccess(
            ['signatureUrl' => $this->signatureService->buildSignatureUrl($personnel)],
            'Signature enregistrée avec succès.',
        );
    }

    #[Route('', name: 'api_me_signature_delete', methods: ['DELETE'])]
    #[IsGranted(AdminPermissions::SIGNATURE_UPDATE)]
    public function delete(#[CurrentUser] ?Personnel $personnel): JsonResponse
    {
        $personnel = $this->requirePersonnel($personnel);
        $this->signatureService->delete($personnel);
        $this->entityManager->flush();

        return $this->apiSuccess(
            ['signatureUrl' => null],
            'Signature supprimée avec succès.',
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
