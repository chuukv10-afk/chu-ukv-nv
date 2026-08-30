<?php

namespace App\Controller\Api\Referentiel;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/v1/referentiel/maladies')]
#[IsGranted('ROLE_PERSONNEL')]
final class MaladiesController extends AbstractController
{
    #[Route('', name: 'api_referentiel_maladies_index', methods: ['GET'])]
    public function index(): JsonResponse
    {
        return $this->json([
            'data' => [],
            'message' => 'Module maladies — à implémenter.',
        ]);
    }
}
