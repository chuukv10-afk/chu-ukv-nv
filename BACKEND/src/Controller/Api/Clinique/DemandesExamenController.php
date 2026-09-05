<?php

namespace App\Controller\Api\Clinique;

use App\Controller\Api\Trait\JsonResponseTrait;
use App\DTO\Clinique\CreateDiagnosticInput;
use App\DTO\Clinique\DemandeExamenListQuery;
use App\DTO\Clinique\SaisieResultatInput;
use App\Security\Permission\CliniquePermissions;
use App\Service\Clinique\DemandeExamenService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryString;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/v1/clinique/demandes-examen')]
#[IsGranted('ROLE_PERSONNEL')]
final class DemandesExamenController extends AbstractController
{
    use JsonResponseTrait;

    public function __construct(
        private readonly DemandeExamenService $demandeExamenService,
    ) {
    }

    #[Route('', name: 'api_clinique_demandes_examen_index', methods: ['GET'])]
    #[IsGranted(CliniquePermissions::DEMANDE_EXAMEN_READ)]
    public function index(
        #[MapQueryString] DemandeExamenListQuery $query = new DemandeExamenListQuery(),
    ): JsonResponse {
        $result = $this->demandeExamenService->paginateGlobal($query);

        return $this->apiPaginatedSuccess(
            $result->items,
            $result->page,
            $result->limit,
            $result->total,
            'Demandes d\'examen récupérées avec succès.',
        );
    }

    #[Route('/meta', name: 'api_clinique_demandes_examen_meta', methods: ['GET'])]
    #[IsGranted(CliniquePermissions::DEMANDE_EXAMEN_READ)]
    public function meta(): JsonResponse
    {
        return $this->apiSuccess(
            $this->demandeExamenService->buildMeta(),
            'Métadonnées demandes d\'examen récupérées avec succès.',
        );
    }

    #[Route('/{id}/prendre-en-charge', name: 'api_clinique_demandes_examen_take', methods: ['POST'], requirements: ['id' => '\d+'])]
    #[IsGranted(CliniquePermissions::DEMANDE_EXAMEN_SAISIE_RESULTAT)]
    public function prendreEnCharge(int $id): JsonResponse
    {
        return $this->apiSuccess(
            $this->demandeExamenService->prendreEnCharge($id),
            'Demande prise en charge.',
        );
    }

    #[Route('/{id}/resultat', name: 'api_clinique_demandes_examen_resultat', methods: ['POST'], requirements: ['id' => '\d+'])]
    #[IsGranted(CliniquePermissions::DEMANDE_EXAMEN_SAISIE_RESULTAT)]
    public function saisirResultat(
        int $id,
        #[MapRequestPayload] SaisieResultatInput $input,
    ): JsonResponse {
        return $this->apiSuccess(
            $this->demandeExamenService->saisirResultat($id, $input),
            'Résultat enregistré.',
        );
    }

    #[Route('/{id}/valider', name: 'api_clinique_demandes_examen_valider', methods: ['POST'], requirements: ['id' => '\d+'])]
    #[IsGranted(CliniquePermissions::DEMANDE_EXAMEN_VALIDATE)]
    public function valider(int $id): JsonResponse
    {
        return $this->apiSuccess(
            $this->demandeExamenService->valider($id),
            'Résultat validé.',
        );
    }

    #[Route('/{id}/annuler', name: 'api_clinique_demandes_examen_annuler', methods: ['POST'], requirements: ['id' => '\d+'])]
    #[IsGranted(CliniquePermissions::DEMANDE_EXAMEN_CANCEL)]
    public function annuler(int $id): JsonResponse
    {
        return $this->apiSuccess(
            $this->demandeExamenService->annuler($id),
            'Demande annulée.',
        );
    }

    #[Route('/{id}/diagnostics', name: 'api_clinique_demandes_examen_diagnostic', methods: ['POST'], requirements: ['id' => '\d+'])]
    #[IsGranted(CliniquePermissions::DIAGNOSTIC_CREATE)]
    public function addDiagnostic(
        int $id,
        #[MapRequestPayload] CreateDiagnosticInput $input,
    ): JsonResponse {
        return $this->apiSuccess(
            $this->demandeExamenService->createLinkedDiagnostic($id, $input),
            'Diagnostic lié à la demande.',
            Response::HTTP_CREATED,
        );
    }
}
