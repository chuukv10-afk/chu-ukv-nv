<?php

namespace App\Controller\Api\Pharmacie;

use App\Controller\Api\Trait\JsonResponseTrait;
use App\DTO\Pharmacie\PharmacieListQuery;
use App\DTO\Pharmacie\UpdateLotInput;
use App\Security\Permission\PharmaciePermissions;
use App\Service\Pharmacie\LotService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapQueryString;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/v1/pharmacie/lots')]
#[IsGranted('ROLE_PERSONNEL')]
final class LotsController extends AbstractController
{
    use JsonResponseTrait;

    #[Route('', name: 'api_pharmacie_lots_index', methods: ['GET'])]
    #[IsGranted(PharmaciePermissions::LOT_READ)]
    public function index(
        LotService $lotService,
        #[MapQueryString] PharmacieListQuery $query = new PharmacieListQuery(),
    ): JsonResponse {
        $result = $lotService->paginate($query);

        return $this->apiPaginatedSuccess($result->items, $result->page, $result->limit, $result->total, 'Liste des lots récupérée avec succès.');
    }

    #[Route('/alertes', name: 'api_pharmacie_lots_alertes', methods: ['GET'])]
    #[IsGranted(PharmaciePermissions::LOT_READ)]
    public function alertes(LotService $lotService): JsonResponse
    {
        return $this->apiSuccess($lotService->alertes(), 'Alertes de stock récupérées avec succès.');
    }

    #[Route('/vendables/{medicamentId}', name: 'api_pharmacie_lots_vendables', methods: ['GET'], requirements: ['medicamentId' => '\d+'])]
    public function vendables(LotService $lotService, int $medicamentId): JsonResponse
    {
        return $this->apiSuccess($lotService->vendables($medicamentId), 'Lots vendables récupérés avec succès.');
    }

    #[Route('/{id}', name: 'api_pharmacie_lots_update', methods: ['PUT'], requirements: ['id' => '\d+'])]
    #[IsGranted(PharmaciePermissions::LOT_UPDATE)]
    public function update(
        LotService $lotService,
        int $id,
        #[MapRequestPayload] UpdateLotInput $input,
    ): JsonResponse {
        return $this->apiSuccess(
            $lotService->serializeSummary($lotService->update($id, $input)),
            'Lot mis à jour avec succès.',
        );
    }
}
