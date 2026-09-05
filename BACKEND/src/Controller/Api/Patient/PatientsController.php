<?php

namespace App\Controller\Api\Patient;

use App\Controller\Api\Trait\ExportResponseTrait;
use App\Controller\Api\Trait\JsonResponseTrait;
use App\DTO\Patient\CreatePatientInput;
use App\DTO\Patient\PatientListQuery;
use App\DTO\Patient\UpdateDpiInput;
use App\DTO\Patient\UpdatePatientInput;
use App\Entity\Dpi;
use App\Entity\Patient;
use App\Security\Permission\PatientPermissions;
use App\Service\Export\TableExportService;
use App\Service\Patient\PatientService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryString;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/v1/patients')]
#[IsGranted('ROLE_PERSONNEL')]
final class PatientsController extends AbstractController
{
    use JsonResponseTrait;
    use ExportResponseTrait;

    #[Route('', name: 'api_patients_index', methods: ['GET'])]
    #[IsGranted(PatientPermissions::PATIENT_READ)]
    public function index(
        PatientService $patientService,
        #[MapQueryString] PatientListQuery $query = new PatientListQuery(),
    ): JsonResponse {
        $result = $patientService->paginate($query);

        return $this->apiPaginatedSuccess(
            $result->items,
            $result->page,
            $result->limit,
            $result->total,
            'Liste des patients récupérée avec succès.',
        );
    }

    #[Route('/meta', name: 'api_patients_meta', methods: ['GET'])]
    #[IsGranted(PatientPermissions::PATIENT_READ)]
    public function meta(): JsonResponse
    {
        return $this->apiSuccess(
            [
                'statuses' => Patient::getStatuses(),
                'sexes' => Patient::getSexes(),
                'dpiStatuts' => Dpi::getStatuts(),
            ],
            'Métadonnées patients récupérées avec succès.',
        );
    }

    #[Route('/export', name: 'api_patients_export', methods: ['GET'])]
    #[IsGranted(PatientPermissions::PATIENT_EXPORT)]
    public function export(
        Request $request,
        PatientService $patientService,
        TableExportService $tableExportService,
        #[MapQueryString] PatientListQuery $query = new PatientListQuery(),
    ): Response {
        return $this->createTableExportResponse(
            $request,
            $tableExportService,
            [
                'N° dossier',
                'Nom',
                'Post-nom',
                'Prénom',
                'Date naissance',
                'Sexe',
                'Téléphone',
                'Statut patient',
                'Statut DPI',
                'Groupe sanguin',
                'Adresse',
                'Personne à prévenir',
                'Contact urgence',
            ],
            $patientService->buildExportRows($query),
            'Liste des patients',
            'patients',
            'Aucun patient trouvé pour les filtres sélectionnés.',
        );
    }

    #[Route('/{id}', name: 'api_patients_show', methods: ['GET'], requirements: ['id' => '[0-9a-f\-]{36}'])]
    #[IsGranted(PatientPermissions::PATIENT_READ)]
    public function show(PatientService $patientService, string $id): JsonResponse
    {
        return $this->apiSuccess(
            $patientService->serializeDetail($patientService->getById($id)),
            'Patient récupéré avec succès.',
        );
    }

    #[Route('', name: 'api_patients_create', methods: ['POST'])]
    #[IsGranted(PatientPermissions::PATIENT_CREATE)]
    public function create(
        PatientService $patientService,
        #[MapRequestPayload] CreatePatientInput $input,
    ): JsonResponse {
        return $this->apiSuccess(
            $patientService->serializeDetail($patientService->create($input)),
            'Patient enregistré avec succès. Dossier DPI créé automatiquement.',
            Response::HTTP_CREATED,
        );
    }

    #[Route('/{id}', name: 'api_patients_update', methods: ['PUT'], requirements: ['id' => '[0-9a-f\-]{36}'])]
    #[IsGranted(PatientPermissions::PATIENT_UPDATE)]
    public function update(
        PatientService $patientService,
        string $id,
        #[MapRequestPayload] UpdatePatientInput $input,
    ): JsonResponse {
        return $this->apiSuccess(
            $patientService->serializeDetail($patientService->update($id, $input)),
            'Patient mis à jour avec succès.',
        );
    }

    #[Route('/{id}', name: 'api_patients_delete', methods: ['DELETE'], requirements: ['id' => '[0-9a-f\-]{36}'])]
    #[IsGranted(PatientPermissions::PATIENT_DELETE)]
    public function delete(PatientService $patientService, string $id): JsonResponse
    {
        $patientService->delete($id);

        return $this->apiSuccess(message: 'Patient supprimé avec succès.');
    }

    #[Route('/{id}/dpi', name: 'api_patients_dpi_show', methods: ['GET'], requirements: ['id' => '[0-9a-f\-]{36}'])]
    #[IsGranted(PatientPermissions::DPI_READ)]
    public function showDpi(PatientService $patientService, string $id): JsonResponse
    {
        return $this->apiSuccess(
            $patientService->serializeDpiWithPatient($patientService->getDpiByPatientId($id)),
            'Dossier patient (DPI) récupéré avec succès.',
        );
    }

    #[Route('/{id}/dpi', name: 'api_patients_dpi_update', methods: ['PUT'], requirements: ['id' => '[0-9a-f\-]{36}'])]
    #[IsGranted(PatientPermissions::DPI_UPDATE)]
    public function updateDpi(
        PatientService $patientService,
        string $id,
        #[MapRequestPayload] UpdateDpiInput $input,
    ): JsonResponse {
        return $this->apiSuccess(
            $patientService->serializeDpiWithPatient($patientService->updateDpi($id, $input)),
            'Statut du dossier patient mis à jour avec succès.',
        );
    }
}
