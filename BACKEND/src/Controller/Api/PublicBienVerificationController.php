<?php

namespace App\Controller\Api;

use App\Controller\Api\Trait\JsonResponseTrait;
use App\Service\Intendance\BienPatrimonialService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/v1/public/biens')]
final class PublicBienVerificationController extends AbstractController
{
    use JsonResponseTrait;

    public function __construct(
        private readonly BienPatrimonialService $bienPatrimonialService,
    ) {
    }

    #[Route('', name: 'api_public_bien_verify', methods: ['GET'])]
    #[IsGranted('PUBLIC_ACCESS')]
    public function verify(Request $request): JsonResponse
    {
        $code = trim((string) $request->query->get('code', ''));
        if ('' === $code) {
            throw new BadRequestHttpException('Indiquez le code inventaire.');
        }

        $bien = $this->bienPatrimonialService->getByCode($code);

        return $this->apiSuccess(
            $this->bienPatrimonialService->serializePublicVerification($bien),
            'Équipement vérifié.',
        );
    }
}
