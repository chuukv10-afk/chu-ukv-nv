<?php

namespace App\Controller\Api\Admin;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/v1/admin/roles')]
#[IsGranted('ROLE_PERSONNEL')]
final class RolesController extends AbstractController
{
    #[Route('', name: 'api_admin_roles_index', methods: ['GET'])]
    public function index(): JsonResponse
    {
        return $this->json([
            'data' => [],
            'message' => 'Module rôles — à implémenter.',
        ]);
    }
}
