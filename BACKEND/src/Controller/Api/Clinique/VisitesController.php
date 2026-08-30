<?php

namespace App\Controller\Api\Clinique;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/v1/clinique/visites')]
#[IsGranted('ROLE_PERSONNEL')]
final class VisitesController extends AbstractController
{
    #[Route('', name: 'api_clinique_visites_index', methods: ['GET'])]
    public function index(): JsonResponse
    {
        return $this->json([
            'data' => [],
            'message' => 'Module visites — à implémenter.',
        ]);
    }
}
