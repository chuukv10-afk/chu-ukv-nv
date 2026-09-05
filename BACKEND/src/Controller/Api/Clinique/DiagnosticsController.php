<?php

namespace App\Controller\Api\Clinique;

use App\Controller\Api\Trait\JsonResponseTrait;
use App\Security\Permission\CliniquePermissions;
use App\Service\Clinique\DiagnosticService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/v1/clinique/diagnostics')]
#[IsGranted('ROLE_PERSONNEL')]
final class DiagnosticsController extends AbstractController
{
    use JsonResponseTrait;

    public function __construct(
        private readonly DiagnosticService $diagnosticService,
    ) {
    }

    #[Route('/meta', name: 'api_clinique_diagnostics_meta', methods: ['GET'])]
    #[IsGranted(CliniquePermissions::DIAGNOSTIC_READ)]
    public function meta(): JsonResponse
    {
        return $this->apiSuccess(
            $this->diagnosticService->buildMeta(),
            'Métadonnées diagnostics récupérées avec succès.',
        );
    }
}
