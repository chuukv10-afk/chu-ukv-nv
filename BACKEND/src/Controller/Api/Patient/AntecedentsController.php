<?php

namespace App\Controller\Api\Patient;

use App\Controller\Api\Trait\JsonResponseTrait;
use App\DTO\Patient\AntecedentListQuery;
use App\DTO\Patient\CreateAntecedentInput;
use App\Security\Permission\PatientPermissions;
use App\Service\Patient\AntecedentService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryString;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/v1/patients/{patientId}/antecedents', requirements: ['patientId' => '[0-9a-f\-]{36}'])]
#[IsGranted('ROLE_PERSONNEL')]
final class AntecedentsController extends AbstractController
{
    use JsonResponseTrait;

    public function __construct(
        private readonly AntecedentService $antecedentService,
    ) {
    }

    #[Route('', name: 'api_patients_antecedents_index', methods: ['GET'])]
    #[IsGranted(PatientPermissions::DPI_ANTECEDENT_READ)]
    public function index(
        string $patientId,
        #[MapQueryString] AntecedentListQuery $query = new AntecedentListQuery(),
    ): JsonResponse {
        $result = $this->antecedentService->paginateByPatientId($patientId, $query);

        return $this->apiPaginatedSuccess(
            $result->items,
            $result->page,
            $result->limit,
            $result->total,
            'Antécédents récupérés avec succès.',
        );
    }

    #[Route('', name: 'api_patients_antecedents_create', methods: ['POST'])]
    #[IsGranted(PatientPermissions::DPI_ANTECEDENT_CREATE)]
    public function create(
        string $patientId,
        #[MapRequestPayload] CreateAntecedentInput $input,
    ): JsonResponse {
        return $this->apiSuccess(
            $this->antecedentService->createForPatient($patientId, $input),
            'Antécédent ajouté avec succès.',
            Response::HTTP_CREATED,
        );
    }

    #[Route('/{id}', name: 'api_patients_antecedents_delete', methods: ['DELETE'], requirements: ['id' => '\d+'])]
    #[IsGranted(PatientPermissions::DPI_ANTECEDENT_DELETE)]
    public function delete(string $patientId, int $id): JsonResponse
    {
        $this->antecedentService->deleteForPatient($patientId, $id);

        return $this->apiSuccess(message: 'Antécédent supprimé avec succès.');
    }
}
