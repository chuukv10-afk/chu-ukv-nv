<?php

namespace App\Controller\Api\Facturation;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/v1/facturation/actes-financiers-visites')]
#[IsGranted('ROLE_PERSONNEL')]
final class FacturationVisiteController extends AbstractController
{
    #[Route('', name: 'api_facturation_actes_financiers_visites_index', methods: ['GET'])]
    public function index(): JsonResponse
    {
        return $this->json([
            'data' => [],
            'message' => 'Module facturation visite — à implémenter.',
        ]);
    }
}
