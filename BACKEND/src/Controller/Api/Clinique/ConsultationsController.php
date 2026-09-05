<?php

namespace App\Controller\Api\Clinique;

use App\Controller\Api\Trait\JsonResponseTrait;
use App\DTO\Clinique\CloseConsultationInput;
use App\DTO\Clinique\ConsultationListQuery;
use App\DTO\Clinique\CreateConsultationInput;
use App\DTO\Clinique\CreateDemandeExamenInput;
use App\DTO\Clinique\CreateDiagnosticInput;
use App\DTO\Clinique\CreateVisiteMesureInput;
use App\DTO\Clinique\CreateVisiteMesurePriseInput;
use App\DTO\Clinique\DemandeExamenListQuery;
use App\DTO\Clinique\DiagnosticListQuery;
use App\DTO\Clinique\UpdateConsultationInput;
use App\DTO\Patient\AntecedentListQuery;
use App\DTO\Patient\CreateAntecedentInput;
use App\Security\Permission\CliniquePermissions;
use App\Service\Clinique\ConsultationService;
use App\Service\Clinique\DemandeExamenService;
use App\Service\Clinique\DiagnosticService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryString;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/v1/clinique/consultations')]
#[IsGranted('ROLE_PERSONNEL')]
final class ConsultationsController extends AbstractController
{
    use JsonResponseTrait;

    public function __construct(
        private readonly ConsultationService $consultationService,
        private readonly DiagnosticService $diagnosticService,
        private readonly DemandeExamenService $demandeExamenService,
    ) {
    }

    #[Route('', name: 'api_clinique_consultations_index', methods: ['GET'])]
    #[IsGranted(CliniquePermissions::CONSULTATION_READ)]
    public function index(
        #[MapQueryString] ConsultationListQuery $query = new ConsultationListQuery(),
    ): JsonResponse {
        $result = $this->consultationService->paginate($query);

        return $this->apiPaginatedSuccess(
            $result->items,
            $result->page,
            $result->limit,
            $result->total,
            'Liste des consultations récupérée avec succès.',
        );
    }

    #[Route('/meta', name: 'api_clinique_consultations_meta', methods: ['GET'])]
    #[IsGranted(CliniquePermissions::CONSULTATION_READ)]
    public function meta(): JsonResponse
    {
        return $this->apiSuccess(
            $this->consultationService->buildMeta(),
            'Métadonnées consultations récupérées avec succès.',
        );
    }

    #[Route('/{id}/vitals', name: 'api_clinique_consultations_vitals', methods: ['GET'], requirements: ['id' => '\d+'])]
    #[IsGranted(CliniquePermissions::CONSULTATION_READ)]
    public function vitals(int $id): JsonResponse
    {
        return $this->apiSuccess(
            $this->consultationService->getVitalsContext($id),
            'Constantes vitales récupérées avec succès.',
        );
    }

    #[Route('/{id}/vitals', name: 'api_clinique_consultations_vitals_add', methods: ['POST'], requirements: ['id' => '\d+'])]
    #[IsGranted(CliniquePermissions::CONSULTATION_UPDATE)]
    public function addVital(
        int $id,
        #[MapRequestPayload] CreateVisiteMesureInput $input,
    ): JsonResponse {
        $this->consultationService->addVisiteMesure($id, $input);

        return $this->apiSuccess(
            $this->consultationService->getVitalsContext($id),
            'Constante vitale ajoutée avec succès.',
            Response::HTTP_CREATED,
        );
    }

    #[Route('/{id}/vitals/prise', name: 'api_clinique_consultations_vitals_prise', methods: ['POST'], requirements: ['id' => '\d+'])]
    #[IsGranted(CliniquePermissions::CONSULTATION_UPDATE)]
    public function addVitalPrise(
        int $id,
        #[MapRequestPayload] CreateVisiteMesurePriseInput $input,
    ): JsonResponse {
        return $this->apiSuccess(
            $this->consultationService->addVisiteMesurePrise($id, $input),
            'Prise de constantes enregistrée avec succès.',
            Response::HTTP_CREATED,
        );
    }

    #[Route('/{id}/antecedents', name: 'api_clinique_consultations_antecedents', methods: ['GET'], requirements: ['id' => '\d+'])]
    #[IsGranted(CliniquePermissions::CONSULTATION_READ)]
    public function antecedents(
        int $id,
        #[MapQueryString] AntecedentListQuery $query = new AntecedentListQuery(),
    ): JsonResponse {
        $result = $this->consultationService->paginateAntecedents($id, $query);

        return $this->apiPaginatedSuccess(
            $result->items,
            $result->page,
            $result->limit,
            $result->total,
            'Antécédents récupérés avec succès.',
        );
    }

    #[Route('/{id}/antecedents', name: 'api_clinique_consultations_antecedents_add', methods: ['POST'], requirements: ['id' => '\d+'])]
    #[IsGranted(CliniquePermissions::CONSULTATION_UPDATE)]
    public function addAntecedent(
        int $id,
        #[MapRequestPayload] CreateAntecedentInput $input,
    ): JsonResponse {
        return $this->apiSuccess(
            $this->consultationService->createAntecedent($id, $input),
            'Antécédent ajouté avec succès.',
            Response::HTTP_CREATED,
        );
    }

    #[Route('/{id}/antecedents/{antecedentId}', name: 'api_clinique_consultations_antecedents_delete', methods: ['DELETE'], requirements: ['id' => '\d+', 'antecedentId' => '\d+'])]
    #[IsGranted(CliniquePermissions::CONSULTATION_UPDATE)]
    public function deleteAntecedent(int $id, int $antecedentId): JsonResponse
    {
        $this->consultationService->deleteAntecedent($id, $antecedentId);

        return $this->apiSuccess(message: 'Antécédent supprimé avec succès.');
    }

    #[Route('/{id}/diagnostics', name: 'api_clinique_consultations_diagnostics', methods: ['GET'], requirements: ['id' => '\d+'])]
    #[IsGranted(CliniquePermissions::DIAGNOSTIC_READ)]
    public function diagnostics(
        int $id,
        #[MapQueryString] DiagnosticListQuery $query = new DiagnosticListQuery(),
    ): JsonResponse {
        $result = $this->diagnosticService->paginateByConsultation($id, $query);

        return $this->apiPaginatedSuccess(
            $result->items,
            $result->page,
            $result->limit,
            $result->total,
            'Diagnostics récupérés avec succès.',
        );
    }

    #[Route('/{id}/diagnostics', name: 'api_clinique_consultations_diagnostics_add', methods: ['POST'], requirements: ['id' => '\d+'])]
    #[IsGranted(CliniquePermissions::DIAGNOSTIC_CREATE)]
    public function addDiagnostic(
        int $id,
        #[MapRequestPayload] CreateDiagnosticInput $input,
    ): JsonResponse {
        return $this->apiSuccess(
            $this->diagnosticService->createForConsultation($id, $input),
            'Diagnostic ajouté avec succès.',
            Response::HTTP_CREATED,
        );
    }

    #[Route('/{id}/demandes-examen', name: 'api_clinique_consultations_demandes_examen', methods: ['GET'], requirements: ['id' => '\d+'])]
    #[IsGranted(CliniquePermissions::DEMANDE_EXAMEN_READ)]
    public function demandesExamen(
        int $id,
        #[MapQueryString] DemandeExamenListQuery $query = new DemandeExamenListQuery(),
    ): JsonResponse {
        $result = $this->demandeExamenService->paginateByConsultation($id, $query);

        return $this->apiPaginatedSuccess(
            $result->items,
            $result->page,
            $result->limit,
            $result->total,
            'Demandes d\'examen récupérées avec succès.',
        );
    }

    #[Route('/{id}/demandes-examen', name: 'api_clinique_consultations_demandes_examen_add', methods: ['POST'], requirements: ['id' => '\d+'])]
    #[IsGranted(CliniquePermissions::DEMANDE_EXAMEN_CREATE)]
    public function addDemandeExamen(
        int $id,
        #[MapRequestPayload] CreateDemandeExamenInput $input,
    ): JsonResponse {
        return $this->apiSuccess(
            $this->demandeExamenService->createForConsultation($id, $input),
            'Demande d\'examen créée avec succès.',
            Response::HTTP_CREATED,
        );
    }

    #[Route('/{id}/diagnostics/{diagnosticId}', name: 'api_clinique_consultations_diagnostics_delete', methods: ['DELETE'], requirements: ['id' => '\d+', 'diagnosticId' => '\d+'])]
    #[IsGranted(CliniquePermissions::DIAGNOSTIC_DELETE)]
    public function deleteDiagnostic(int $id, int $diagnosticId): JsonResponse
    {
        $this->diagnosticService->deleteForConsultation($id, $diagnosticId);

        return $this->apiSuccess(message: 'Diagnostic supprimé avec succès.');
    }

    #[Route('/{id}/close', name: 'api_clinique_consultations_close', methods: ['POST'], requirements: ['id' => '\d+'])]
    #[IsGranted(CliniquePermissions::CONSULTATION_CLOSE)]
    public function close(
        int $id,
        #[MapRequestPayload] CloseConsultationInput $input,
    ): JsonResponse {
        return $this->apiSuccess(
            $this->consultationService->serializeDetail($this->consultationService->close($id, $input)),
            'Consultation clôturée avec succès.',
        );
    }

    #[Route('/{id}', name: 'api_clinique_consultations_show', methods: ['GET'], requirements: ['id' => '\d+'])]
    #[IsGranted(CliniquePermissions::CONSULTATION_READ)]
    public function show(int $id): JsonResponse
    {
        return $this->apiSuccess(
            $this->consultationService->serializeDetail($this->consultationService->getById($id)),
            'Consultation récupérée avec succès.',
        );
    }

    #[Route('', name: 'api_clinique_consultations_create', methods: ['POST'])]
    #[IsGranted(CliniquePermissions::CONSULTATION_CREATE)]
    public function create(
        #[MapRequestPayload] CreateConsultationInput $input,
    ): JsonResponse {
        return $this->apiSuccess(
            $this->consultationService->serializeDetail($this->consultationService->create($input)),
            'Consultation créée avec succès.',
            Response::HTTP_CREATED,
        );
    }

    #[Route('/{id}', name: 'api_clinique_consultations_update', methods: ['PUT'], requirements: ['id' => '\d+'])]
    #[IsGranted(CliniquePermissions::CONSULTATION_UPDATE)]
    public function update(
        int $id,
        #[MapRequestPayload] UpdateConsultationInput $input,
    ): JsonResponse {
        return $this->apiSuccess(
            $this->consultationService->serializeDetail($this->consultationService->update($id, $input)),
            'Consultation mise à jour avec succès.',
        );
    }

    #[Route('/{id}', name: 'api_clinique_consultations_delete', methods: ['DELETE'], requirements: ['id' => '\d+'])]
    #[IsGranted(CliniquePermissions::CONSULTATION_DELETE)]
    public function delete(int $id): JsonResponse
    {
        $this->consultationService->delete($id);

        return $this->apiSuccess(message: 'Consultation supprimée avec succès.');
    }
}
