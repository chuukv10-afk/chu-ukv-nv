<?php

namespace App\Controller\Api\Facturation;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

final class FacturationVisiteController extends AbstractController
{
    #[Route('/api/facturation/facturation/visite', name: 'app_api_facturation_facturation_visite')]
    public function index(): JsonResponse
    {
        return $this->json([
            'message' => 'Welcome to your new controller!',
            'path' => 'src/Controller/Api/Facturation/FacturationVisiteController.php',
        ]);
    }
}
