<?php

namespace App\Controller\Api\Pharmacie;

use App\Controller\Api\Trait\JsonResponseTrait;
use App\DTO\Pharmacie\PharmacieListQuery;
use App\Security\Permission\PharmaciePermissions;
use App\Service\Pharmacie\FicheStockService;
use App\Service\Pharmacie\MouvementStockService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryString;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/v1/pharmacie/mouvements')]
#[IsGranted('ROLE_PERSONNEL')]
final class MouvementsController extends AbstractController
{
    use JsonResponseTrait;

    #[Route('', name: 'api_pharmacie_mouvements_index', methods: ['GET'])]
    #[IsGranted(PharmaciePermissions::MOUVEMENT_READ)]
    public function index(
        MouvementStockService $mouvementStockService,
        #[MapQueryString] PharmacieListQuery $query = new PharmacieListQuery(),
    ): JsonResponse {
        $result = $mouvementStockService->paginate($query);

        return $this->apiPaginatedSuccess($result->items, $result->page, $result->limit, $result->total, 'Journal de stock récupéré avec succès.');
    }

    #[Route('/fiches-stock/export', name: 'api_pharmacie_mouvements_fiches_export', methods: ['GET'])]
    #[IsGranted(PharmaciePermissions::MOUVEMENT_EXPORT)]
    public function exportFiches(
        Request $request,
        FicheStockService $ficheStockService,
        #[MapQueryString] PharmacieListQuery $query = new PharmacieListQuery(),
    ): Response {
        return $ficheStockService->export($request, $query);
    }
}
