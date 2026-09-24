<?php

namespace App\Controller\Api\Facturation;

use App\Controller\Api\Trait\ExportResponseTrait;
use App\Controller\Api\Trait\JsonResponseTrait;
use App\DTO\Facturation\FacturationListQuery;
use App\DTO\Facturation\UpsertActeFinancierInput;
use App\Exception\ConflictException;
use App\Security\Permission\FacturationPermissions;
use App\Service\Export\TableExportService;
use App\Service\Facturation\ActeFinancierService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryString;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/v1/facturation/actes-financiers')]
#[IsGranted('ROLE_PERSONNEL')]
final class ActesFinanciersController extends AbstractController
{
    use JsonResponseTrait;
    use ExportResponseTrait;

    #[Route('', name: 'api_facturation_actes_financiers_index', methods: ['GET'])]
    #[IsGranted(FacturationPermissions::ACTE_READ)]
    public function index(
        ActeFinancierService $acteFinancierService,
        #[MapQueryString] FacturationListQuery $query = new FacturationListQuery(),
    ): JsonResponse {
        $result = $acteFinancierService->paginate($query);

        return $this->apiPaginatedSuccess(
            $result->items,
            $result->page,
            $result->limit,
            $result->total,
            'Grille tarifaire récupérée avec succès.',
        );
    }

    #[Route('/meta', name: 'api_facturation_actes_financiers_meta', methods: ['GET'])]
    #[IsGranted(FacturationPermissions::ACTE_READ)]
    public function meta(ActeFinancierService $acteFinancierService): JsonResponse
    {
        return $this->apiSuccess($acteFinancierService->buildMeta(), 'Métadonnées grille tarifaire récupérées.');
    }

    #[Route('', name: 'api_facturation_actes_financiers_create', methods: ['POST'])]
    #[IsGranted(FacturationPermissions::ACTE_CREATE)]
    public function create(
        ActeFinancierService $acteFinancierService,
        #[MapRequestPayload] UpsertActeFinancierInput $input,
    ): JsonResponse {
        return $this->apiSuccess(
            $acteFinancierService->serializeSummary($acteFinancierService->create($input)),
            'Acte tarifaire créé avec succès.',
            Response::HTTP_CREATED,
        );
    }

    #[Route('/export', name: 'api_facturation_actes_financiers_export', methods: ['GET'])]
    #[IsGranted(FacturationPermissions::ACTE_EXPORT)]
    public function export(
        Request $request,
        ActeFinancierService $acteFinancierService,
        TableExportService $tableExportService,
        #[MapQueryString] FacturationListQuery $query = new FacturationListQuery(),
    ): Response {
        $format = strtolower(trim((string) $request->query->get('format', 'xlsx')));

        return $this->createTableExportResponse(
            $request,
            $tableExportService,
            $acteFinancierService->exportHeaders($format),
            $acteFinancierService->buildExportRows($query, $format),
            'Grille tarifaire CHU-UKV',
            'grille-tarifaire',
            'Aucun acte trouvé pour les filtres sélectionnés.',
            pdfOrientation: 'landscape',
        );
    }

    #[Route('/import', name: 'api_facturation_actes_financiers_import', methods: ['POST'])]
    #[IsGranted(FacturationPermissions::ACTE_IMPORT)]
    public function import(Request $request, ActeFinancierService $acteFinancierService): JsonResponse
    {
        $file = $request->files->get('file');
        if (!$file instanceof UploadedFile) {
            throw new ConflictException('Envoyez un fichier Excel (champ file).');
        }

        $result = $acteFinancierService->importFromUpload($file);

        return $this->apiSuccess(
            $result,
            sprintf('Import terminé : %d acte(s) créé(s), %d mis à jour.', $result['imported'], $result['updated']),
        );
    }

    #[Route('/{id}', name: 'api_facturation_actes_financiers_show', methods: ['GET'], requirements: ['id' => '\d+'])]
    #[IsGranted(FacturationPermissions::ACTE_READ)]
    public function show(ActeFinancierService $acteFinancierService, int $id): JsonResponse
    {
        return $this->apiSuccess(
            $acteFinancierService->serializeSummary($acteFinancierService->getById($id)),
            'Acte tarifaire récupéré avec succès.',
        );
    }

    #[Route('/{id}', name: 'api_facturation_actes_financiers_update', methods: ['PUT'], requirements: ['id' => '\d+'])]
    #[IsGranted(FacturationPermissions::ACTE_UPDATE)]
    public function update(
        ActeFinancierService $acteFinancierService,
        int $id,
        #[MapRequestPayload] UpsertActeFinancierInput $input,
    ): JsonResponse {
        return $this->apiSuccess(
            $acteFinancierService->serializeSummary($acteFinancierService->update($id, $input)),
            'Acte tarifaire mis à jour avec succès.',
        );
    }

    #[Route('/{id}', name: 'api_facturation_actes_financiers_delete', methods: ['DELETE'], requirements: ['id' => '\d+'])]
    #[IsGranted(FacturationPermissions::ACTE_DELETE)]
    public function delete(ActeFinancierService $acteFinancierService, int $id): JsonResponse
    {
        $acteFinancierService->delete($id);

        return $this->apiSuccess(message: 'Acte tarifaire supprimé avec succès.');
    }
}
