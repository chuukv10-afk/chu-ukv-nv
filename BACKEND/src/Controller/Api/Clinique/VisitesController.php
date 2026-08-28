<?php

namespace App\Controller\Api\Clinique;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

final class VisitesController extends AbstractController
{
    #[Route('/api/patient/visites', name: 'app_api_patient_visites')]
    public function index(): JsonResponse
    {
        return $this->json([
            'message' => 'Welcome to your new controller!',
            'path' => 'src/Controller/Api/Patient/VisitesController.php',
        ]);
    }
}
