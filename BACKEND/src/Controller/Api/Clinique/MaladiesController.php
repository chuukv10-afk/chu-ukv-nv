<?php

namespace App\Controller\Api\Clinique;

use App\Controller\Api\Trait\ExportResponseTrait;
use App\Controller\Api\Trait\JsonResponseTrait;
use App\DTO\Clinique\BulkDeleteMaladieInput;
use App\DTO\Clinique\CreateMaladieInput;
use App\DTO\Clinique\MaladieListQuery;
use App\DTO\Clinique\UpdateMaladieInput;
use App\Security\Permission\CliniquePermissions;
use App\Service\Export\TableExportService;
use App\Service\Referentiel\MaladieService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryString;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/v1/clinique/maladies')]
final class MaladiesController extends AbstractController
{
    use JsonResponseTrait;
    use ExportResponseTrait;

    public function __construct(
        private readonly MaladieService $maladieService,
    ) {
    }

    #[Route('', name: 'api_clinique_maladies_index', methods: ['GET'])]
    #[IsGranted(CliniquePermissions::MALADIE_READ)]
    public function index(#[MapQueryString] MaladieListQuery $query = new MaladieListQuery()): JsonResponse
    {
        $result = $this->maladieService->paginate($query);

        return $this->apiPaginatedSuccess(
            $result->items,
            $result->page,
            $result->limit,
            $result->total,
            'Liste des maladies CIM-10 récupérée avec succès.',
        );
    }

    #[Route('/chapitres', name: 'api_clinique_maladies_chapitres', methods: ['GET'])]
    #[IsGranted(CliniquePermissions::MALADIE_READ)]
    public function chapitres(): JsonResponse
    {
        return $this->apiSuccess(
            $this->maladieService->listChapitres(),
            'Chapitres CIM-10 récupérés avec succès.',
        );
    }

    #[Route('/export', name: 'api_clinique_maladies_export', methods: ['GET'])]
    #[IsGranted(CliniquePermissions::MALADIE_EXPORT)]
    public function export(
        Request $request,
        TableExportService $tableExportService,
        #[MapQueryString] MaladieListQuery $query = new MaladieListQuery(),
    ): Response {
        return $this->createTableExportResponse(
            $request,
            $tableExportService,
            ['N°', 'Code CIM-10', 'Libellé', 'Chapitre'],
            $this->maladieService->buildExportRows($query),
            'Liste des maladies CIM-10',
            'maladies_cim10',
            'Aucune maladie trouvée pour les filtres sélectionnés.',
        );
    }

    #[Route('/bulk-delete', name: 'api_clinique_maladies_bulk_delete', methods: ['POST'])]
    #[IsGranted(CliniquePermissions::MALADIE_DELETE)]
    public function bulkDelete(#[MapRequestPayload] BulkDeleteMaladieInput $input): JsonResponse
    {
        $result = $this->maladieService->deleteMany($input);

        return $this->apiSuccess(
            $result,
            sprintf(
                '%d maladie(s) supprimée(s)%s.',
                $result['deleted'],
                $result['blocked'] > 0 ? sprintf(', %d non supprimée(s) (utilisées)', $result['blocked']) : '',
            ),
        );
    }

    #[Route('/{id}', name: 'api_clinique_maladies_show', methods: ['GET'])]
    #[IsGranted(CliniquePermissions::MALADIE_READ)]
    public function show(int $id): JsonResponse
    {
        return $this->apiSuccess(
            $this->maladieService->serializeSummary($this->maladieService->getById($id)),
            'Maladie CIM-10 récupérée avec succès.',
        );
    }

    #[Route('', name: 'api_clinique_maladies_create', methods: ['POST'])]
    #[IsGranted(CliniquePermissions::MALADIE_CREATE)]
    public function create(#[MapRequestPayload] CreateMaladieInput $input): JsonResponse
    {
        return $this->apiSuccess(
            $this->maladieService->serializeSummary($this->maladieService->create($input)),
            'Maladie CIM-10 créée avec succès.',
            Response::HTTP_CREATED,
        );
    }

    #[Route('/{id}', name: 'api_clinique_maladies_update', methods: ['PUT'])]
    #[IsGranted(CliniquePermissions::MALADIE_UPDATE)]
    public function update(int $id, #[MapRequestPayload] UpdateMaladieInput $input): JsonResponse
    {
        return $this->apiSuccess(
            $this->maladieService->serializeSummary($this->maladieService->update($id, $input)),
            'Maladie CIM-10 mise à jour avec succès.',
        );
    }

    #[Route('/{id}', name: 'api_clinique_maladies_delete', methods: ['DELETE'])]
    #[IsGranted(CliniquePermissions::MALADIE_DELETE)]
    public function delete(int $id): JsonResponse
    {
        $this->maladieService->delete($id);

        return $this->apiSuccess(message: 'Maladie CIM-10 supprimée avec succès.');
    }
}
