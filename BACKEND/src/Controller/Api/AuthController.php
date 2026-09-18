<?php

namespace App\Controller\Api;

use App\Controller\Api\Trait\JsonResponseTrait;
use App\Entity\Personnel;
use App\Service\Personnel\MeProfileService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/v1')]
#[IsGranted('ROLE_PERSONNEL')]
final class AuthController extends AbstractController
{
    use JsonResponseTrait;

    public function __construct(
        private readonly MeProfileService $meProfileService,
    ) {
    }

    #[Route('/me', name: 'api_me', methods: ['GET'])]
    public function me(#[CurrentUser] ?Personnel $personnel): JsonResponse
    {
        if (!$personnel instanceof Personnel) {
            return $this->json(['message' => 'Non authentifié.'], JsonResponse::HTTP_UNAUTHORIZED);
        }

        return $this->apiSuccess(
            $this->meProfileService->serialize($personnel),
            'Profil récupéré avec succès.',
        );
    }
}
