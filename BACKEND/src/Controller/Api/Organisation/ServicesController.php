<?php

namespace App\Controller\Api\Organisation;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/v1/organisation/services')]
#[IsGranted('ROLE_PERSONNEL')]
final class ServicesController extends AbstractController
{
    #[Route('', name: 'api_organisation_services_index', methods: ['GET'])]
    public function index(): JsonResponse
    {
        return $this->json([
            'data' => [],
            'message' => 'Module services — à implémenter.',
        ]);
    }
}
