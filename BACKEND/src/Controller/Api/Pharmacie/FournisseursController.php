<?php

namespace App\Controller\Api\Pharmacie;

use App\Controller\Api\Trait\JsonResponseTrait;
use App\DTO\Pharmacie\CreateFournisseurInput;
use App\DTO\Pharmacie\PharmacieListQuery;
use App\DTO\Pharmacie\UpdateFournisseurInput;
use App\Entity\Fournisseur;
use App\Security\Permission\PharmaciePermissions;
use App\Service\Pharmacie\FournisseurService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryString;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/v1/pharmacie/fournisseurs')]
#[IsGranted('ROLE_PERSONNEL')]
final class FournisseursController extends AbstractController
{
    use JsonResponseTrait;

    #[Route('', name: 'api_pharmacie_fournisseurs_index', methods: ['GET'])]
    #[IsGranted(PharmaciePermissions::FOURNISSEUR_READ)]
    public function index(
        FournisseurService $fournisseurService,
        #[MapQueryString] PharmacieListQuery $query = new PharmacieListQuery(),
    ): JsonResponse {
        $result = $fournisseurService->paginate($query);

        return $this->apiPaginatedSuccess($result->items, $result->page, $result->limit, $result->total, 'Liste des fournisseurs récupérée avec succès.');
    }

    #[Route('/actifs', name: 'api_pharmacie_fournisseurs_actifs', methods: ['GET'])]
    public function actifs(FournisseurService $fournisseurService): JsonResponse
    {
        return $this->apiSuccess($fournisseurService->listActifs(), 'Fournisseurs actifs récupérés avec succès.');
    }

    #[Route('/meta', name: 'api_pharmacie_fournisseurs_meta', methods: ['GET'])]
    #[IsGranted(PharmaciePermissions::FOURNISSEUR_READ)]
    public function meta(): JsonResponse
    {
        return $this->apiSuccess(['statuts' => Fournisseur::getStatuts()], 'Métadonnées fournisseurs récupérées.');
    }

    #[Route('/{id}', name: 'api_pharmacie_fournisseurs_show', methods: ['GET'], requirements: ['id' => '\d+'])]
    #[IsGranted(PharmaciePermissions::FOURNISSEUR_READ)]
    public function show(FournisseurService $fournisseurService, int $id): JsonResponse
    {
        return $this->apiSuccess($fournisseurService->serializeSummary($fournisseurService->getById($id)), 'Fournisseur récupéré avec succès.');
    }

    #[Route('', name: 'api_pharmacie_fournisseurs_create', methods: ['POST'])]
    #[IsGranted(PharmaciePermissions::FOURNISSEUR_CREATE)]
    public function create(FournisseurService $fournisseurService, #[MapRequestPayload] CreateFournisseurInput $input): JsonResponse
    {
        return $this->apiSuccess($fournisseurService->serializeSummary($fournisseurService->create($input)), 'Fournisseur créé avec succès.', Response::HTTP_CREATED);
    }

    #[Route('/{id}', name: 'api_pharmacie_fournisseurs_update', methods: ['PUT'], requirements: ['id' => '\d+'])]
    #[IsGranted(PharmaciePermissions::FOURNISSEUR_UPDATE)]
    public function update(FournisseurService $fournisseurService, int $id, #[MapRequestPayload] UpdateFournisseurInput $input): JsonResponse
    {
        return $this->apiSuccess($fournisseurService->serializeSummary($fournisseurService->update($id, $input)), 'Fournisseur mis à jour avec succès.');
    }

    #[Route('/{id}', name: 'api_pharmacie_fournisseurs_delete', methods: ['DELETE'], requirements: ['id' => '\d+'])]
    #[IsGranted(PharmaciePermissions::FOURNISSEUR_DELETE)]
    public function delete(FournisseurService $fournisseurService, int $id): JsonResponse
    {
        $fournisseurService->delete($id);

        return $this->apiSuccess(message: 'Fournisseur supprimé avec succès.');
    }
}
