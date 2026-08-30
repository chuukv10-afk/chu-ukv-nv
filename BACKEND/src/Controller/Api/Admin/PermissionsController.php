<?php

namespace App\Controller\Api\Admin;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/v1/admin/permissions')]
#[IsGranted('ROLE_PERSONNEL')]
final class PermissionsController extends AbstractController
{
    #[Route('', name: 'api_admin_permissions_index', methods: ['GET'])]
    public function index(): JsonResponse
    {
        return $this->json([
            'data' => [],
            'message' => 'Module permissions — à implémenter.',
        ]);
    }
}
