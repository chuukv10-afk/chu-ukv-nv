<?php

namespace App\Controller\Api\Referentiel;

use App\Controller\Api\Trait\ExportResponseTrait;
use App\Controller\Api\Trait\JsonResponseTrait;
use App\DTO\Referentiel\CreateSpecialiteInput;
use App\DTO\Referentiel\ReferentielListQuery;
use App\DTO\Referentiel\UpdateSpecialiteInput;
use App\Security\Permission\ReferentielPermissions;
use App\Service\Export\TableExportService;
use App\Service\Referentiel\SpecialiteService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryString;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/v1/referentiel/specialites')]
final class SpecialitesController extends AbstractController
{
    use JsonResponseTrait;
    use ExportResponseTrait;

    public function __construct(
        private readonly SpecialiteService $specialiteService,
    ) {
    }

    #[Route('', name: 'api_referentiel_specialites_index', methods: ['GET'])]
    #[IsGranted(ReferentielPermissions::SPECIALITE_READ)]
    public function index(#[MapQueryString] ReferentielListQuery $query = new ReferentielListQuery()): JsonResponse
    {
        $result = $this->specialiteService->paginate($query);

        return $this->apiPaginatedSuccess(
            $result->items,
            $result->page,
            $result->limit,
            $result->total,
            'Liste des spécialités récupérée avec succès.',
        );
    }

    #[Route('/export', name: 'api_referentiel_specialites_export', methods: ['GET'])]
    #[IsGranted(ReferentielPermissions::SPECIALITE_EXPORT)]
    public function export(
        Request $request,
        TableExportService $tableExportService,
        #[MapQueryString] ReferentielListQuery $query = new ReferentielListQuery(),
    ): Response {
        return $this->createTableExportResponse(
            $request,
            $tableExportService,
            ['N°', 'Code', 'Libellé', 'Nb personnel'],
            $this->specialiteService->buildExportRows($query),
            'Liste des spécialités',
            'specialites',
            'Aucune spécialité trouvée pour les filtres sélectionnés.',
        );
    }

    #[Route('/{id}', name: 'api_referentiel_specialites_show', methods: ['GET'])]
    #[IsGranted(ReferentielPermissions::SPECIALITE_READ)]
    public function show(int $id): JsonResponse
    {
        $specialite = $this->specialiteService->getById($id);
        $this->denyAccessUnlessGranted(ReferentielPermissions::SPECIALITE_READ, $specialite);

        return $this->apiSuccess(
            $this->specialiteService->serializeSummary($specialite),
            'Spécialité récupérée avec succès.',
        );
    }

    #[Route('', name: 'api_referentiel_specialites_create', methods: ['POST'])]
    #[IsGranted(ReferentielPermissions::SPECIALITE_CREATE)]
    public function create(#[MapRequestPayload] CreateSpecialiteInput $input): JsonResponse
    {
        return $this->apiSuccess(
            $this->specialiteService->serializeSummary($this->specialiteService->create($input)),
            'Spécialité créée avec succès.',
            Response::HTTP_CREATED,
        );
    }

    #[Route('/{id}', name: 'api_referentiel_specialites_update', methods: ['PUT'])]
    #[IsGranted(ReferentielPermissions::SPECIALITE_UPDATE)]
    public function update(int $id, #[MapRequestPayload] UpdateSpecialiteInput $input): JsonResponse
    {
        $specialite = $this->specialiteService->getById($id);
        $this->denyAccessUnlessGranted(ReferentielPermissions::SPECIALITE_UPDATE, $specialite);

        return $this->apiSuccess(
            $this->specialiteService->serializeSummary($this->specialiteService->update($id, $input)),
            'Spécialité mise à jour avec succès.',
        );
    }

    #[Route('/{id}', name: 'api_referentiel_specialites_delete', methods: ['DELETE'])]
    #[IsGranted(ReferentielPermissions::SPECIALITE_DELETE)]
    public function delete(int $id): JsonResponse
    {
        $specialite = $this->specialiteService->getById($id);
        $this->denyAccessUnlessGranted(ReferentielPermissions::SPECIALITE_DELETE, $specialite);

        $this->specialiteService->delete($id);

        return $this->apiSuccess(message: 'Spécialité supprimée avec succès.');
    }
}
