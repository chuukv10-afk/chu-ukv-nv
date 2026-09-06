<?php

namespace App\Controller\Api\Pharmacie;

use App\Controller\Api\Trait\JsonResponseTrait;
use App\DTO\Pharmacie\AnnulerVenteInput;
use App\DTO\Pharmacie\PharmacieListQuery;
use App\DTO\Pharmacie\UpsertVenteInput;
use App\Security\Permission\PharmaciePermissions;
use App\Service\Pharmacie\VenteService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryString;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/v1/pharmacie/ventes')]
#[IsGranted('ROLE_PERSONNEL')]
final class VentesController extends AbstractController
{
    use JsonResponseTrait;

    #[Route('', name: 'api_pharmacie_ventes_index', methods: ['GET'])]
    #[IsGranted(PharmaciePermissions::VENTE_READ)]
    public function index(
        VenteService $venteService,
        #[MapQueryString] PharmacieListQuery $query = new PharmacieListQuery(),
    ): JsonResponse {
        $result = $venteService->paginate($query);

        return $this->apiPaginatedSuccess($result->items, $result->page, $result->limit, $result->total, 'Liste des ventes récupérée avec succès.');
    }

    #[Route('/{id}', name: 'api_pharmacie_ventes_show', methods: ['GET'], requirements: ['id' => '\d+'])]
    #[IsGranted(PharmaciePermissions::VENTE_READ)]
    public function show(VenteService $venteService, int $id): JsonResponse
    {
        return $this->apiSuccess($venteService->serializeDetail($venteService->getById($id)), 'Vente récupérée avec succès.');
    }

    #[Route('', name: 'api_pharmacie_ventes_create', methods: ['POST'])]
    #[IsGranted(PharmaciePermissions::VENTE_CREATE)]
    public function create(VenteService $venteService, #[MapRequestPayload] UpsertVenteInput $input): JsonResponse
    {
        return $this->apiSuccess($venteService->serializeDetail($venteService->create($input)), 'Vente créée avec succès.', Response::HTTP_CREATED);
    }

    #[Route('/{id}', name: 'api_pharmacie_ventes_update', methods: ['PUT'], requirements: ['id' => '\d+'])]
    #[IsGranted(PharmaciePermissions::VENTE_UPDATE)]
    public function update(VenteService $venteService, int $id, #[MapRequestPayload] UpsertVenteInput $input): JsonResponse
    {
        return $this->apiSuccess($venteService->serializeDetail($venteService->update($id, $input)), 'Vente mise à jour avec succès.');
    }

    #[Route('/{id}/valider', name: 'api_pharmacie_ventes_valider', methods: ['POST'], requirements: ['id' => '\d+'])]
    #[IsGranted(PharmaciePermissions::VENTE_VALIDER)]
    public function valider(VenteService $venteService, int $id): JsonResponse
    {
        return $this->apiSuccess($venteService->serializeDetail($venteService->valider($id)), 'Vente validée, stock décrémenté.');
    }

    #[Route('/{id}/annuler', name: 'api_pharmacie_ventes_annuler', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function annuler(
        VenteService $venteService,
        int $id,
        #[MapRequestPayload] AnnulerVenteInput $input = new AnnulerVenteInput(),
    ): JsonResponse {
        return $this->apiSuccess($venteService->serializeDetail($venteService->annuler($id, $input)), 'Vente annulée, stock repris.');
    }

    #[Route('/{id}', name: 'api_pharmacie_ventes_delete', methods: ['DELETE'], requirements: ['id' => '\d+'])]
    #[IsGranted(PharmaciePermissions::VENTE_DELETE)]
    public function delete(VenteService $venteService, int $id): JsonResponse
    {
        $venteService->delete($id);

        return $this->apiSuccess(message: 'Brouillon de vente supprimé.');
    }
}
