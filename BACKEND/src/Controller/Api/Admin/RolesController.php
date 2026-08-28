<?php

namespace App\Controller\Api\Admin;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/v1/admin/personnels')]
#[IsGranted('ROLE_PERSONNEL')]
final class RolesController extends AbstractController
{
    #[Route('/api/admin/roles', name: 'app_api_admin_roles')]
    public function index(): JsonResponse
    {
        return $this->json([
            'message' => 'Welcome to your new controller!',
            'path' => 'src/Controller/Api/Admin/RolesController.php',
        ]);
    }
}
