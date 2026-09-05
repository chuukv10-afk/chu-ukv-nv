<?php

namespace App\Controller\Api\Patient;

use App\Controller\Api\Trait\JsonResponseTrait;
use App\DTO\Clinique\DiagnosticListQuery;
use App\Security\Permission\CliniquePermissions;
use App\Service\Clinique\DiagnosticService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapQueryString;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/v1/patients/{patientId}/diagnostics', requirements: ['patientId' => '[0-9a-f\-]{36}'])]
#[IsGranted('ROLE_PERSONNEL')]
final class PatientDiagnosticsController extends AbstractController
{
    use JsonResponseTrait;

    public function __construct(
        private readonly DiagnosticService $diagnosticService,
    ) {
    }

    #[Route('', name: 'api_patients_diagnostics_index', methods: ['GET'])]
    #[IsGranted(CliniquePermissions::DIAGNOSTIC_READ)]
    public function index(
        string $patientId,
        #[MapQueryString] DiagnosticListQuery $query = new DiagnosticListQuery(),
    ): JsonResponse {
        $result = $this->diagnosticService->paginateByPatientId($patientId, $query);

        return $this->apiPaginatedSuccess(
            $result->items,
            $result->page,
            $result->limit,
            $result->total,
            'Historique des diagnostics récupéré avec succès.',
        );
    }
}
