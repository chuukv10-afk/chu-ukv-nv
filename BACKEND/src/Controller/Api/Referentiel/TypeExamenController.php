<?php

namespace App\Controller\Api\Referentiel;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/v1/referentiel/types-examen')]
#[IsGranted('ROLE_PERSONNEL')]
final class TypeExamenController extends AbstractController
{
    #[Route('', name: 'api_referentiel_types_examen_index', methods: ['GET'])]
    public function index(): JsonResponse
    {
        return $this->json([
            'data' => [],
            'message' => 'Module types d\'examen — à implémenter.',
        ]);
    }
}
