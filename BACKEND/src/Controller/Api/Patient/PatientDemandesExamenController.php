<?php

namespace App\Controller\Api\Patient;

use App\Controller\Api\Trait\JsonResponseTrait;
use App\DTO\Clinique\DemandeExamenListQuery;
use App\Security\Permission\CliniquePermissions;
use App\Service\Clinique\DemandeExamenService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapQueryString;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/v1/patients/{patientId}/demandes-examen', requirements: ['patientId' => '[0-9a-f\-]{36}'])]
#[IsGranted('ROLE_PERSONNEL')]
final class PatientDemandesExamenController extends AbstractController
{
    use JsonResponseTrait;

    public function __construct(
        private readonly DemandeExamenService $demandeExamenService,
    ) {
    }

    #[Route('', name: 'api_patients_demandes_examen_index', methods: ['GET'])]
    #[IsGranted(CliniquePermissions::DEMANDE_EXAMEN_READ)]
    public function index(
        string $patientId,
        #[MapQueryString] DemandeExamenListQuery $query = new DemandeExamenListQuery(),
    ): JsonResponse {
        $result = $this->demandeExamenService->paginateByPatientId($patientId, $query);

        return $this->apiPaginatedSuccess(
            $result->items,
            $result->page,
            $result->limit,
            $result->total,
            'Historique des demandes d\'examen récupéré avec succès.',
        );
    }
}
