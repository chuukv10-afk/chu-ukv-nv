<?php

namespace App\Controller\Api\Clinique;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/v1/clinique/examens')]
#[IsGranted('ROLE_PERSONNEL')]
final class ExamensController extends AbstractController
{
    #[Route('', name: 'api_clinique_examens_index', methods: ['GET'])]
    public function index(): JsonResponse
    {
        return $this->json([
            'data' => [],
            'message' => 'Module examens — à implémenter.',
        ]);
    }
}
