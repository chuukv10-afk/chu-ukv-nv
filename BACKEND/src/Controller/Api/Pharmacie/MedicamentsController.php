<?php

namespace App\Controller\Api\Pharmacie;

use App\Controller\Api\Trait\ExportResponseTrait;
use App\Controller\Api\Trait\JsonResponseTrait;
use App\DTO\Pharmacie\CreateMedicamentInput;
use App\DTO\Pharmacie\UpdateMedicamentInput;
use App\DTO\Referentiel\ReferentielListQuery;
use App\Entity\Medicament;
use App\Security\Permission\PharmaciePermissions;
use App\Service\Export\TableExportService;
use App\Service\Pharmacie\MedicamentService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryString;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/v1/pharmacie/medicaments')]
#[IsGranted('ROLE_PERSONNEL')]
final class MedicamentsController extends AbstractController
{
    use JsonResponseTrait;
    use ExportResponseTrait;

    #[Route('', name: 'api_pharmacie_medicaments_index', methods: ['GET'])]
    #[IsGranted(PharmaciePermissions::MEDICAMENT_READ)]
    public function index(
        MedicamentService $medicamentService,
        #[MapQueryString] ReferentielListQuery $query = new ReferentielListQuery(),
    ): JsonResponse {
        $result = $medicamentService->paginate($query);

        return $this->apiPaginatedSuccess(
            $result->items,
            $result->page,
            $result->limit,
            $result->total,
            'Liste des médicaments récupérée avec succès.',
        );
    }

    #[Route('/actifs', name: 'api_pharmacie_medicaments_actifs', methods: ['GET'])]
    public function actifs(MedicamentService $medicamentService): JsonResponse
    {
        return $this->apiSuccess(
            $medicamentService->listActifs(),
            'Médicaments actifs récupérés avec succès.',
        );
    }

    #[Route('/export', name: 'api_pharmacie_medicaments_export', methods: ['GET'])]
    #[IsGranted(PharmaciePermissions::MEDICAMENT_EXPORT)]
    public function export(
        Request $request,
        TableExportService $tableExportService,
        MedicamentService $medicamentService,
        #[MapQueryString] ReferentielListQuery $query = new ReferentielListQuery(),
    ): Response {
        $format = strtolower(trim((string) $request->query->get('format', 'xlsx')));

        return $this->createTableExportResponse(
            $request,
            $tableExportService,
            $medicamentService->exportHeaders($format),
            $medicamentService->buildExportRows($query, $format),
            'Catalogue des médicaments',
            'medicaments',
            'Aucun médicament trouvé pour les filtres sélectionnés.',
            pdfOrientation: 'portrait',
        );
    }

    #[Route('/meta', name: 'api_pharmacie_medicaments_meta', methods: ['GET'])]
    #[IsGranted(PharmaciePermissions::MEDICAMENT_READ)]
    public function meta(): JsonResponse
    {
        return $this->apiSuccess(
            ['statuts' => Medicament::getStatuts()],
            'Métadonnées médicaments récupérées avec succès.',
        );
    }

    #[Route('/{id}', name: 'api_pharmacie_medicaments_show', methods: ['GET'], requirements: ['id' => '\d+'])]
    #[IsGranted(PharmaciePermissions::MEDICAMENT_READ)]
    public function show(MedicamentService $medicamentService, int $id): JsonResponse
    {
        return $this->apiSuccess(
            $medicamentService->serializeSummary($medicamentService->getById($id)),
            'Médicament récupéré avec succès.',
        );
    }

    #[Route('', name: 'api_pharmacie_medicaments_create', methods: ['POST'])]
    #[IsGranted(PharmaciePermissions::MEDICAMENT_CREATE)]
    public function create(
        MedicamentService $medicamentService,
        #[MapRequestPayload] CreateMedicamentInput $input,
    ): JsonResponse {
        return $this->apiSuccess(
            $medicamentService->serializeSummary($medicamentService->create($input)),
            'Médicament créé avec succès.',
            Response::HTTP_CREATED,
        );
    }

    #[Route('/{id}', name: 'api_pharmacie_medicaments_update', methods: ['PUT'], requirements: ['id' => '\d+'])]
    #[IsGranted(PharmaciePermissions::MEDICAMENT_UPDATE)]
    public function update(
        MedicamentService $medicamentService,
        int $id,
        #[MapRequestPayload] UpdateMedicamentInput $input,
    ): JsonResponse {
        return $this->apiSuccess(
            $medicamentService->serializeSummary($medicamentService->update($id, $input)),
            'Médicament mis à jour avec succès.',
        );
    }

    #[Route('/{id}', name: 'api_pharmacie_medicaments_delete', methods: ['DELETE'], requirements: ['id' => '\d+'])]
    #[IsGranted(PharmaciePermissions::MEDICAMENT_DELETE)]
    public function delete(MedicamentService $medicamentService, int $id): JsonResponse
    {
        $medicamentService->delete($id);

        return $this->apiSuccess(message: 'Médicament supprimé avec succès.');
    }
}
