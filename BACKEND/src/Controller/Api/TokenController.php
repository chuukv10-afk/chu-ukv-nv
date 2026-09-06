<?php

namespace App\Controller\Api;

use App\Controller\Api\Trait\JsonResponseTrait;
use App\Service\Auth\RefreshTokenService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/v1')]
final class TokenController extends AbstractController
{
    use JsonResponseTrait;

    #[Route('/token/refresh', name: 'api_token_refresh', methods: ['POST'])]
    public function refresh(Request $request, RefreshTokenService $refreshTokenService): JsonResponse
    {
        $payload = json_decode((string) $request->getContent(), true);
        $refreshToken = is_array($payload) ? trim((string) ($payload['refreshToken'] ?? '')) : '';
        if ('' === $refreshToken) {
            throw new BadRequestHttpException('Le refresh token est obligatoire.');
        }

        $tokens = $refreshTokenService->rotate($refreshToken);

        return $this->json([
            'token' => $tokens['token'],
            'refreshToken' => $tokens['refreshToken'],
        ]);
    }
}
