<?php

namespace App\Controller\Api\Pharmacie;

use App\Controller\Api\Trait\JsonResponseTrait;
use App\DTO\Pharmacie\PharmacieListQuery;
use App\Security\Permission\PharmaciePermissions;
use App\Service\Pharmacie\StatistiqueService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapQueryString;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/v1/pharmacie/statistiques')]
#[IsGranted('ROLE_PERSONNEL')]
final class StatistiquesController extends AbstractController
{
    use JsonResponseTrait;

    #[Route('', name: 'api_pharmacie_statistiques_index', methods: ['GET'])]
    #[IsGranted(PharmaciePermissions::STATISTIQUE_READ)]
    public function index(
        StatistiqueService $statistiqueService,
        #[MapQueryString] PharmacieListQuery $query = new PharmacieListQuery(),
    ): JsonResponse {
        return $this->apiSuccess(
            $statistiqueService->build($query),
            'Statistiques pharmacie récupérées avec succès.',
        );
    }
}
