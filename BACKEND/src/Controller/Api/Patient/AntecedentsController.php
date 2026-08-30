<?php

namespace App\Controller\Api\Patient;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/v1/patients/antecedents')]
#[IsGranted('ROLE_PERSONNEL')]
final class AntecedentsController extends AbstractController
{
    #[Route('', name: 'api_patients_antecedents_index', methods: ['GET'])]
    public function index(): JsonResponse
    {
        return $this->json([
            'data' => [],
            'message' => 'Module antécédents — à implémenter.',
        ]);
    }
}
