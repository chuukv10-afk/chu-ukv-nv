<?php

namespace App\Controller\Api\Clinique;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

final class ExamensController extends AbstractController
{
    #[Route('/api/patient/examens', name: 'app_api_patient_examens')]
    public function index(): JsonResponse
    {
        return $this->json([
            'message' => 'Welcome to your new controller!',
            'path' => 'src/Controller/Api/Patient/ExamensController.php',
        ]);
    }
}
