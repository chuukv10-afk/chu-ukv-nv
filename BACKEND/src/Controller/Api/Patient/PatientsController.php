<?php

namespace App\Controller\Api\Patient;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

final class PatientsController extends AbstractController
{
    #[Route('/api/patient/patients', name: 'app_api_patient_patients')]
    public function index(): JsonResponse
    {
        return $this->json([
            'message' => 'Welcome to your new controller!',
            'path' => 'src/Controller/Api/Patient/PatientsController.php',
        ]);
    }
}
