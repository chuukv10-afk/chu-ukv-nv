<?php

namespace App\Controller\Api\Facturation;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

final class ActesFinanciersController extends AbstractController
{
    #[Route('/api/facturation/actes/financiers', name: 'app_api_facturation_actes_financiers')]
    public function index(): JsonResponse
    {
        return $this->json([
            'message' => 'Welcome to your new controller!',
            'path' => 'src/Controller/Api/Facturation/ActesFinanciersController.php',
        ]);
    }
}
