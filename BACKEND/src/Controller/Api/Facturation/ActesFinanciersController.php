<?php

namespace App\Controller\Api\Facturation;

use App\Controller\Api\Trait\ExportResponseTrait;
use App\Controller\Api\Trait\JsonResponseTrait;
use App\DTO\Facturation\FacturationListQuery;
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

    #[Route('/export', name: 'api_facturation_actes_financiers_export', methods: ['GET'])]
    #[IsGranted(FacturationPermissions::ACTE_EXPORT)]
    public function export(
        Request $request,
        ActeFinancierService $acteFinancierService,
        TableExportService $tableExportService,
        #[MapQueryString] FacturationListQuery $query = new FacturationListQuery(),
    ): Response {
        return $this->createTableExportResponse(
            $request,
            $tableExportService,
            ['Code', 'Service', 'Sous-catégorie', 'Acte', 'A0', 'A1', 'A', 'B', 'C', 'Unité', 'Statut'],
            $acteFinancierService->buildExportRows($query),
            'Grille tarifaire CHHU',
            'grille-tarifaire',
            'Aucun acte trouvé pour les filtres sélectionnés.',
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
}
