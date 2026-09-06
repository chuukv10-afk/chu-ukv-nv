<?php

namespace App\Controller\Api\Pharmacie;

use App\Controller\Api\Trait\JsonResponseTrait;
use App\DTO\Pharmacie\PharmacieListQuery;
use App\Security\Permission\PharmaciePermissions;
use App\Service\Pharmacie\RecetteService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapQueryString;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/v1/pharmacie/recettes')]
#[IsGranted('ROLE_PERSONNEL')]
final class RecettesController extends AbstractController
{
    use JsonResponseTrait;

    #[Route('', name: 'api_pharmacie_recettes_index', methods: ['GET'])]
    #[IsGranted(PharmaciePermissions::RECETTE_READ)]
    public function index(
        RecetteService $recetteService,
        #[MapQueryString] PharmacieListQuery $query = new PharmacieListQuery(),
    ): JsonResponse {
        return $this->apiSuccess(
            $recetteService->journal($query),
            'Journal des recettes récupéré avec succès.',
        );
    }
}
