<?php

namespace App\Controller\Api\Patient;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

final class AntécédentsController extends AbstractController
{
    #[Route('/api/patient/ant/c/dents', name: 'app_api_patient_ant_c_dents')]
    public function index(): JsonResponse
    {
        return $this->json([
            'message' => 'Welcome to your new controller!',
            'path' => 'src/Controller/Api/Patient/AntécédentsController.php',
        ]);
    }
}
