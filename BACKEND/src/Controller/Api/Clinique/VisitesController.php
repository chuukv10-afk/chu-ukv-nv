<?php

namespace App\Controller\Api\Clinique;

use App\Controller\Api\Trait\ExportResponseTrait;
use App\Controller\Api\Trait\JsonResponseTrait;
use App\DTO\Clinique\CreateVisiteInput;
use App\DTO\Clinique\UpdateVisiteInput;
use App\DTO\Clinique\VisiteListQuery;
use App\Entity\Visite;
use App\Security\Permission\CliniquePermissions;
use App\Service\Clinique\VisiteService;
use App\Service\Export\TableExportService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryString;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/v1/clinique/visites')]
#[IsGranted('ROLE_PERSONNEL')]
final class VisitesController extends AbstractController
{
    use JsonResponseTrait;
    use ExportResponseTrait;

    #[Route('', name: 'api_clinique_visites_index', methods: ['GET'])]
    #[IsGranted(CliniquePermissions::VISITE_READ)]
    public function index(
        VisiteService $visiteService,
        #[MapQueryString] VisiteListQuery $query = new VisiteListQuery(),
    ): JsonResponse {
        $result = $visiteService->paginate($query);

        return $this->apiPaginatedSuccess(
            $result->items,
            $result->page,
            $result->limit,
            $result->total,
            'Liste des visites récupérée avec succès.',
        );
    }

    #[Route('/meta', name: 'api_clinique_visites_meta', methods: ['GET'])]
    #[IsGranted(CliniquePermissions::VISITE_READ)]
    public function meta(VisiteService $visiteService): JsonResponse
    {
        return $this->apiSuccess(
            [
                'statuts' => Visite::getStatuts(),
                'activeStatuts' => Visite::getActiveStatuts(),
                'creatableStatuts' => Visite::getCreatableStatuts(),
                'services' => $visiteService->listServiceOptions(),
                'lits' => $visiteService->listLitOptions(),
            ],
            'Métadonnées visites récupérées avec succès.',
        );
    }

    #[Route('/export', name: 'api_clinique_visites_export', methods: ['GET'])]
    #[IsGranted(CliniquePermissions::VISITE_EXPORT)]
    public function export(
        Request $request,
        VisiteService $visiteService,
        TableExportService $tableExportService,
        #[MapQueryString] VisiteListQuery $query = new VisiteListQuery(),
    ): Response {
        return $this->createTableExportResponse(
            $request,
            $tableExportService,
            [
                'ID',
                'N° dossier',
                'Patient',
                'Service',
                'Statut',
                'Entrée',
                'Sortie',
                'Sortie prévue',
                'Lit',
                'Chambre',
                'Bloc',
            ],
            $visiteService->buildExportRows($query),
            'Liste des visites',
            'visites',
            'Aucune visite trouvée pour les filtres sélectionnés.',
        );
    }

    #[Route('/{id}', name: 'api_clinique_visites_show', methods: ['GET'], requirements: ['id' => '\d+'])]
    #[IsGranted(CliniquePermissions::VISITE_READ)]
    public function show(VisiteService $visiteService, int $id): JsonResponse
    {
        return $this->apiSuccess(
            $visiteService->serializeDetail($visiteService->getById($id)),
            'Visite récupérée avec succès.',
        );
    }

    #[Route('/meta/hospitalisation', name: 'api_clinique_visites_meta_hospitalisation', methods: ['GET'])]
    #[IsGranted(CliniquePermissions::VISITE_UPDATE)]
    public function hospitalisationMeta(
        VisiteService $visiteService,
        Request $request,
    ): JsonResponse {
        $visiteId = $request->query->getInt('visiteId');

        return $this->apiSuccess(
            $visiteService->buildHospitalisationMeta($visiteId > 0 ? $visiteId : null),
            'Métadonnées hospitalisation récupérées avec succès.',
        );
    }

    #[Route('/meta/create', name: 'api_clinique_visites_meta_create', methods: ['GET'])]
    #[IsGranted(CliniquePermissions::VISITE_CREATE)]
    public function createMeta(VisiteService $visiteService): JsonResponse
    {
        return $this->apiSuccess(
            $visiteService->buildCreateMeta(),
            'Métadonnées création visite récupérées avec succès.',
        );
    }

    #[Route('', name: 'api_clinique_visites_create', methods: ['POST'])]
    #[IsGranted(CliniquePermissions::VISITE_CREATE)]
    public function create(
        VisiteService $visiteService,
        #[MapRequestPayload] CreateVisiteInput $input,
    ): JsonResponse {
        return $this->apiSuccess(
            $visiteService->serializeDetail($visiteService->create($input)),
            'Visite créée avec succès.',
            Response::HTTP_CREATED,
        );
    }

    #[Route('/{id}', name: 'api_clinique_visites_update', methods: ['PUT'], requirements: ['id' => '\d+'])]
    #[IsGranted(CliniquePermissions::VISITE_UPDATE)]
    public function update(
        VisiteService $visiteService,
        int $id,
        #[MapRequestPayload] UpdateVisiteInput $input,
    ): JsonResponse {
        return $this->apiSuccess(
            $visiteService->serializeDetail($visiteService->update($id, $input)),
            'Visite mise à jour avec succès.',
        );
    }

    #[Route('/{id}', name: 'api_clinique_visites_delete', methods: ['DELETE'], requirements: ['id' => '\d+'])]
    #[IsGranted(CliniquePermissions::VISITE_DELETE)]
    public function delete(VisiteService $visiteService, int $id): JsonResponse
    {
        $visiteService->delete($id);

        return $this->apiSuccess(message: 'Visite supprimée avec succès.');
    }
}
