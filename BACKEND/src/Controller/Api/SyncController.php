<?php

namespace App\Controller\Api;

use App\Controller\Api\Trait\JsonResponseTrait;
use App\Service\Sync\SyncPullService;
use App\Service\Sync\SyncPushService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/v1/sync')]
#[IsGranted('ROLE_PERSONNEL')]
final class SyncController extends AbstractController
{
    use JsonResponseTrait;

    #[Route('/pull', name: 'api_sync_pull', methods: ['POST'])]
    public function pull(Request $request, SyncPullService $syncPullService): JsonResponse
    {
        $payload = json_decode((string) $request->getContent(), true);
        $modules = is_array($payload['modules'] ?? null) ? $payload['modules'] : [];

        return $this->apiSuccess(
            $syncPullService->pull($modules),
            'Snapshot hors-ligne récupéré avec succès.',
        );
    }

    #[Route('/push', name: 'api_sync_push', methods: ['POST'])]
    public function push(Request $request, SyncPushService $syncPushService): JsonResponse
    {
        $payload = json_decode((string) $request->getContent(), true);
        $mutations = is_array($payload['mutations'] ?? null) ? $payload['mutations'] : [];

        return $this->apiSuccess(
            ['results' => $syncPushService->push($mutations)],
            'Mutations hors-ligne traitées.',
        );
    }
}
