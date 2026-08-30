<?php

namespace App\Controller\Api\Patient;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/v1/patients/dpi')]
#[IsGranted('ROLE_PERSONNEL')]
final class DpiController extends AbstractController
{
    #[Route('', name: 'api_patients_dpi_index', methods: ['GET'])]
    public function index(): JsonResponse
    {
        return $this->json([
            'data' => [],
            'message' => 'Module DPI — à implémenter.',
        ]);
    }
}
