<?php

namespace App\Controller\Api\Referentiel;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

final class TypeExamenController extends AbstractController
{
    #[Route('/api/referentiel/type/examen', name: 'app_api_referentiel_type_examen')]
    public function index(): JsonResponse
    {
        return $this->json([
            'message' => 'Welcome to your new controller!',
            'path' => 'src/Controller/Api/Referentiel/TypeExamenController.php',
        ]);
    }
}
