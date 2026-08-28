<?php

namespace App\Controller\Api\Referentiel;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

final class SpecialitesController extends AbstractController
{
    #[Route('/api/referentiel/specialites', name: 'app_api_referentiel_specialites')]
    public function index(): JsonResponse
    {
        return $this->json([
            'message' => 'Welcome to your new controller!',
            'path' => 'src/Controller/Api/Referentiel/SpecialitesController.php',
        ]);
    }
}
