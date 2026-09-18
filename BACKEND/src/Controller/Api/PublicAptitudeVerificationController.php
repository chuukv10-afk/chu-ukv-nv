<?php

namespace App\Controller\Api;

use App\Controller\Api\Trait\JsonResponseTrait;
use App\Service\Clinique\AptitudeService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/v1/public/aptitude-certificates')]
final class PublicAptitudeVerificationController extends AbstractController
{
    use JsonResponseTrait;

    public function __construct(
        private readonly AptitudeService $aptitudeService,
    ) {
    }

    #[Route('', name: 'api_public_aptitude_verify', methods: ['GET'])]
    #[IsGranted('PUBLIC_ACCESS')]
    public function verify(Request $request): JsonResponse
    {
        $numero = trim((string) $request->query->get('numero', ''));
        if ('' === $numero) {
            throw new BadRequestHttpException('Indiquez le numéro du certificat.');
        }

        $certificat = $this->aptitudeService->verifyOfficialByNumero($numero);

        return $this->apiSuccess(
            $this->aptitudeService->serializePublicVerification($certificat),
            'Certificat vérifié.',
        );
    }
}
