<?php

namespace App\Controller\Api\Organisation;

use App\Controller\Api\Trait\ExportResponseTrait;
use App\Controller\Api\Trait\JsonResponseTrait;
use App\DTO\Organisation\CreateLitInput;
use App\DTO\Organisation\OrganisationListQuery;
use App\DTO\Organisation\UpdateLitInput;
use App\Security\Permission\OrganisationPermissions;
use App\Service\Export\TableExportService;
use App\Service\Organisation\LitService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryString;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/v1/organisation/lits')]
final class LitsController extends AbstractController
{
    use JsonResponseTrait;
    use ExportResponseTrait;

    public function __construct(
        private readonly LitService $litService,
    ) {
    }

    #[Route('', name: 'api_organisation_lits_index', methods: ['GET'])]
    #[IsGranted(OrganisationPermissions::LIT_READ)]
    public function index(#[MapQueryString] OrganisationListQuery $query = new OrganisationListQuery()): JsonResponse
    {
        $result = $this->litService->paginate($query);

        return $this->apiPaginatedSuccess(
            $result->items,
            $result->page,
            $result->limit,
            $result->total,
            'Liste des lits récupérée avec succès.',
        );
    }

    #[Route('/export', name: 'api_organisation_lits_export', methods: ['GET'])]
    #[IsGranted(OrganisationPermissions::LIT_EXPORT)]
    public function export(
        Request $request,
        TableExportService $tableExportService,
        #[MapQueryString] OrganisationListQuery $query = new OrganisationListQuery(),
    ): Response {
        return $this->createTableExportResponse(
            $request,
            $tableExportService,
            ['N°', 'Code', 'N° lit', 'Bloc', 'Chambre', 'Nb visites'],
            $this->litService->buildExportRows($query),
            'Liste des lits',
            'lits',
            'Aucun lit trouvé pour les filtres sélectionnés.',
        );
    }

    #[Route('/{id}', name: 'api_organisation_lits_show', methods: ['GET'])]
    #[IsGranted(OrganisationPermissions::LIT_READ)]
    public function show(int $id): JsonResponse
    {
        $lit = $this->litService->getById($id);
        $this->denyAccessUnlessGranted(OrganisationPermissions::LIT_READ, $lit);

        return $this->apiSuccess(
            $this->litService->serializeSummary($lit),
            'Lit récupéré avec succès.',
        );
    }

    #[Route('', name: 'api_organisation_lits_create', methods: ['POST'])]
    #[IsGranted(OrganisationPermissions::LIT_CREATE)]
    public function create(#[MapRequestPayload] CreateLitInput $input): JsonResponse
    {
        return $this->apiSuccess(
            $this->litService->serializeSummary($this->litService->create($input)),
            'Lit créé avec succès.',
            Response::HTTP_CREATED,
        );
    }

    #[Route('/{id}', name: 'api_organisation_lits_update', methods: ['PUT'])]
    #[IsGranted(OrganisationPermissions::LIT_UPDATE)]
    public function update(int $id, #[MapRequestPayload] UpdateLitInput $input): JsonResponse
    {
        $lit = $this->litService->getById($id);
        $this->denyAccessUnlessGranted(OrganisationPermissions::LIT_UPDATE, $lit);

        return $this->apiSuccess(
            $this->litService->serializeSummary($this->litService->update($id, $input)),
            'Lit mis à jour avec succès.',
        );
    }

    #[Route('/{id}', name: 'api_organisation_lits_delete', methods: ['DELETE'])]
    #[IsGranted(OrganisationPermissions::LIT_DELETE)]
    public function delete(int $id): JsonResponse
    {
        $lit = $this->litService->getById($id);
        $this->denyAccessUnlessGranted(OrganisationPermissions::LIT_DELETE, $lit);

        $this->litService->delete($id);

        return $this->apiSuccess(message: 'Lit supprimé avec succès.');
    }
}
