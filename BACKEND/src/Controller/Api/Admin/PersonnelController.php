<?php

namespace App\Controller\Api\Admin;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/v1/admin/personnels')]
#[IsGranted('ROLE_PERSONNEL')]
final class PersonnelController extends AbstractController
{
    #[Route('', name: 'api_admin_personnels_index', methods: ['GET'])]
    public function index(): JsonResponse
    {
        return $this->json([
            'data' => [],
            'message' => 'Module personnels — à implémenter.',
        ]);
    }
}
