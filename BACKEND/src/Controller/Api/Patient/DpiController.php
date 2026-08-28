<?php

namespace App\Controller\Api\Patient;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

final class DpiController extends AbstractController
{
    #[Route('/api/patient/dpi', name: 'app_api_patient_dpi')]
    public function index(): JsonResponse
    {
        return $this->json([
            'message' => 'Welcome to your new controller!',
            'path' => 'src/Controller/Api/Patient/DpiController.php',
        ]);
    }
}
