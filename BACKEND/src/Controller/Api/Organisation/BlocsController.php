<?php

namespace App\Controller\Api\Organisation;

use App\Controller\Api\Trait\ExportResponseTrait;
use App\Controller\Api\Trait\JsonResponseTrait;
use App\DTO\Organisation\CreateBlocInput;
use App\DTO\Organisation\OrganisationListQuery;
use App\DTO\Organisation\UpdateBlocInput;
use App\Security\Permission\OrganisationPermissions;
use App\Service\Export\TableExportService;
use App\Service\Organisation\BlocService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryString;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/v1/organisation/blocs')]
final class BlocsController extends AbstractController
{
    use JsonResponseTrait;
    use ExportResponseTrait;

    public function __construct(
        private readonly BlocService $blocService,
    ) {
    }

    #[Route('', name: 'api_organisation_blocs_index', methods: ['GET'])]
    #[IsGranted(OrganisationPermissions::BLOC_READ)]
    public function index(#[MapQueryString] OrganisationListQuery $query = new OrganisationListQuery()): JsonResponse
    {
        $result = $this->blocService->paginate($query);

        return $this->apiPaginatedSuccess(
            $result->items,
            $result->page,
            $result->limit,
            $result->total,
            'Liste des blocs récupérée avec succès.',
        );
    }

    #[Route('/export', name: 'api_organisation_blocs_export', methods: ['GET'])]
    #[IsGranted(OrganisationPermissions::BLOC_EXPORT)]
    public function export(
        Request $request,
        TableExportService $tableExportService,
        #[MapQueryString] OrganisationListQuery $query = new OrganisationListQuery(),
    ): Response {
        return $this->createTableExportResponse(
            $request,
            $tableExportService,
            ['N°', 'Code', 'Libellé', 'Nb chambres'],
            $this->blocService->buildExportRows($query),
            'Liste des blocs',
            'blocs',
            'Aucun bloc trouvé pour les filtres sélectionnés.',
        );
    }

    #[Route('/{id}', name: 'api_organisation_blocs_show', methods: ['GET'])]
    #[IsGranted(OrganisationPermissions::BLOC_READ)]
    public function show(int $id): JsonResponse
    {
        $bloc = $this->blocService->getById($id);
        $this->denyAccessUnlessGranted(OrganisationPermissions::BLOC_READ, $bloc);

        return $this->apiSuccess(
            $this->blocService->serializeSummary($bloc),
            'Bloc récupéré avec succès.',
        );
    }

    #[Route('', name: 'api_organisation_blocs_create', methods: ['POST'])]
    #[IsGranted(OrganisationPermissions::BLOC_CREATE)]
    public function create(#[MapRequestPayload] CreateBlocInput $input): JsonResponse
    {
        return $this->apiSuccess(
            $this->blocService->serializeSummary($this->blocService->create($input)),
            'Bloc créé avec succès.',
            Response::HTTP_CREATED,
        );
    }

    #[Route('/{id}', name: 'api_organisation_blocs_update', methods: ['PUT'])]
    #[IsGranted(OrganisationPermissions::BLOC_UPDATE)]
    public function update(int $id, #[MapRequestPayload] UpdateBlocInput $input): JsonResponse
    {
        $bloc = $this->blocService->getById($id);
        $this->denyAccessUnlessGranted(OrganisationPermissions::BLOC_UPDATE, $bloc);

        return $this->apiSuccess(
            $this->blocService->serializeSummary($this->blocService->update($id, $input)),
            'Bloc mis à jour avec succès.',
        );
    }

    #[Route('/{id}', name: 'api_organisation_blocs_delete', methods: ['DELETE'])]
    #[IsGranted(OrganisationPermissions::BLOC_DELETE)]
    public function delete(int $id): JsonResponse
    {
        $bloc = $this->blocService->getById($id);
        $this->denyAccessUnlessGranted(OrganisationPermissions::BLOC_DELETE, $bloc);

        $this->blocService->delete($id);

        return $this->apiSuccess(message: 'Bloc supprimé avec succès.');
    }
}
