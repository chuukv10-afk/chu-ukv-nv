<?php

namespace App\Controller\Api\Pharmacie;

use App\Controller\Api\Trait\ExportResponseTrait;
use App\Controller\Api\Trait\JsonResponseTrait;
use App\DTO\Pharmacie\CompterInventaireLigneInput;
use App\DTO\Pharmacie\CompterInventaireProduitInput;
use App\DTO\Pharmacie\CreateInventairePharmacieInput;
use App\DTO\Pharmacie\PharmacieListQuery;
use App\Security\Permission\PharmaciePermissions;
use App\Service\Export\TableExportService;
use App\Service\Pharmacie\InventairePharmacieService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryString;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/v1/pharmacie/inventaires')]
#[IsGranted('ROLE_PERSONNEL')]
final class InventairesPharmacieController extends AbstractController
{
    use JsonResponseTrait;
    use ExportResponseTrait;

    #[Route('', name: 'api_pharmacie_inventaires_index', methods: ['GET'])]
    #[IsGranted(PharmaciePermissions::INVENTAIRE_READ)]
    public function index(
        InventairePharmacieService $inventaireService,
        #[MapQueryString] PharmacieListQuery $query = new PharmacieListQuery(),
    ): JsonResponse {
        $result = $inventaireService->paginate($query);

        return $this->apiPaginatedSuccess($result->items, $result->page, $result->limit, $result->total, 'Liste des campagnes d\'inventaire récupérée avec succès.');
    }

    #[Route('/{id}', name: 'api_pharmacie_inventaires_show', methods: ['GET'], requirements: ['id' => '\d+'])]
    #[IsGranted(PharmaciePermissions::INVENTAIRE_READ)]
    public function show(InventairePharmacieService $inventaireService, int $id): JsonResponse
    {
        return $this->apiSuccess($inventaireService->serializeDetail($inventaireService->getById($id)), 'Campagne d\'inventaire récupérée avec succès.');
    }

    #[Route('', name: 'api_pharmacie_inventaires_create', methods: ['POST'])]
    #[IsGranted(PharmaciePermissions::INVENTAIRE_CREATE)]
    public function create(
        InventairePharmacieService $inventaireService,
        #[MapRequestPayload] CreateInventairePharmacieInput $input,
    ): JsonResponse {
        return $this->apiSuccess(
            $inventaireService->serializeDetail($inventaireService->create($input)),
            'Campagne d\'inventaire ouverte. Les lots en stock ont été figés.',
            Response::HTTP_CREATED,
        );
    }

    #[Route('/{id}/export', name: 'api_pharmacie_inventaires_export', methods: ['GET'], requirements: ['id' => '\d+'])]
    #[IsGranted(PharmaciePermissions::INVENTAIRE_READ)]
    public function export(
        Request $request,
        TableExportService $tableExportService,
        InventairePharmacieService $inventaireService,
        int $id,
    ): Response {
        $inventaire = $inventaireService->getById($id);

        return $this->createTableExportResponse(
            $request,
            $tableExportService,
            $inventaireService->exportHeaders(),
            $inventaireService->buildExportRows($inventaire),
            $inventaireService->exportTitle($inventaire),
            $inventaireService->exportFilenamePrefix($inventaire),
            'Aucune ligne d\'inventaire.',
            pdfOrientation: 'landscape',
        );
    }

    #[Route('/{id}/produits/{medicamentId}/corriger', name: 'api_pharmacie_inventaires_corriger_produit', methods: ['POST'], requirements: ['id' => '\d+', 'medicamentId' => '\d+'])]
    #[IsGranted(PharmaciePermissions::INVENTAIRE_SAISIR)]
    public function corrigerProduit(
        InventairePharmacieService $inventaireService,
        int $id,
        int $medicamentId,
        #[MapRequestPayload] CompterInventaireProduitInput $input = new CompterInventaireProduitInput(),
    ): JsonResponse {
        return $this->apiSuccess(
            $inventaireService->serializeDetail($inventaireService->corrigerProduit($id, $medicamentId, $input)),
            'Lot mis à jour.',
        );
    }

    #[Route('/{id}/produits/{medicamentId}/compter', name: 'api_pharmacie_inventaires_compter_produit', methods: ['POST'], requirements: ['id' => '\d+', 'medicamentId' => '\d+'])]
    #[IsGranted(PharmaciePermissions::INVENTAIRE_SAISIR)]
    public function compterProduit(
        InventairePharmacieService $inventaireService,
        int $id,
        int $medicamentId,
        #[MapRequestPayload] CompterInventaireProduitInput $input = new CompterInventaireProduitInput(),
    ): JsonResponse {
        return $this->apiSuccess(
            $inventaireService->serializeDetail($inventaireService->compterProduit($id, $medicamentId, $input)),
            'Comptage enregistré.',
        );
    }

    #[Route('/{id}/lignes/{ligneId}/compter', name: 'api_pharmacie_inventaires_compter_ligne', methods: ['POST'], requirements: ['id' => '\d+', 'ligneId' => '\d+'])]
    #[IsGranted(PharmaciePermissions::INVENTAIRE_SAISIR)]
    public function compterLigne(
        InventairePharmacieService $inventaireService,
        int $id,
        int $ligneId,
        #[MapRequestPayload] CompterInventaireLigneInput $input = new CompterInventaireLigneInput(),
    ): JsonResponse {
        return $this->apiSuccess(
            $inventaireService->serializeDetail($inventaireService->compterLigne($id, $ligneId, $input)),
            'Lot marqué comme compté.',
        );
    }

    #[Route('/{id}/ecarter-non-comptes', name: 'api_pharmacie_inventaires_ecarter', methods: ['POST'], requirements: ['id' => '\d+'])]
    #[IsGranted(PharmaciePermissions::INVENTAIRE_ECARTER)]
    public function ecarterNonComptes(InventairePharmacieService $inventaireService, int $id): JsonResponse
    {
        return $this->apiSuccess(
            $inventaireService->serializeDetail($inventaireService->ecarterNonComptes($id)),
            'Médicaments non comptés écartés et passés en inactif.',
        );
    }

    #[Route('/{id}/cloturer', name: 'api_pharmacie_inventaires_cloturer', methods: ['POST'], requirements: ['id' => '\d+'])]
    #[IsGranted(PharmaciePermissions::INVENTAIRE_CLOTURER)]
    public function cloturer(InventairePharmacieService $inventaireService, int $id): JsonResponse
    {
        return $this->apiSuccess(
            $inventaireService->serializeDetail($inventaireService->cloturer($id)),
            'Campagne d\'inventaire clôturée.',
        );
    }

    #[Route('/{id}', name: 'api_pharmacie_inventaires_delete', methods: ['DELETE'], requirements: ['id' => '\d+'])]
    #[IsGranted(PharmaciePermissions::INVENTAIRE_CREATE)]
    public function delete(InventairePharmacieService $inventaireService, int $id): JsonResponse
    {
        $inventaireService->delete($id);

        return $this->apiSuccess(message: 'Campagne d\'inventaire supprimée.');
    }
}
