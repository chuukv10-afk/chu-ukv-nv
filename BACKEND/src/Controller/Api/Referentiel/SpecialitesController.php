<?php

namespace App\Controller\Api\Referentiel;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/v1/referentiel/specialites')]
#[IsGranted('ROLE_PERSONNEL')]
final class SpecialitesController extends AbstractController
{
    #[Route('', name: 'api_referentiel_specialites_index', methods: ['GET'])]
    public function index(): JsonResponse
    {
        return $this->json([
            'data' => [],
            'message' => 'Module spécialités — à implémenter.',
        ]);
    }
}
