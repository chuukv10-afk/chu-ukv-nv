<?php

namespace App\Controller\Api\Facturation;

use App\Controller\Api\Trait\JsonResponseTrait;
use App\DTO\Facturation\CreateStructureInput;
use App\DTO\Facturation\FacturationListQuery;
use App\DTO\Facturation\UpdateStructureInput;
use App\Entity\CategorieTarifaire;
use App\Entity\Structure;
use App\Security\Permission\FacturationPermissions;
use App\Service\Facturation\StructureService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryString;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/v1/facturation/structures')]
#[IsGranted('ROLE_PERSONNEL')]
final class StructuresController extends AbstractController
{
    use JsonResponseTrait;

    #[Route('', name: 'api_facturation_structures_index', methods: ['GET'])]
    #[IsGranted(FacturationPermissions::STRUCTURE_READ)]
    public function index(
        StructureService $structureService,
        #[MapQueryString] FacturationListQuery $query = new FacturationListQuery(),
    ): JsonResponse {
        $result = $structureService->paginate($query);

        return $this->apiPaginatedSuccess($result->items, $result->page, $result->limit, $result->total, 'Liste des structures récupérée avec succès.');
    }

    #[Route('/actifs', name: 'api_facturation_structures_actifs', methods: ['GET'])]
    public function actifs(
        StructureService $structureService,
        #[MapQueryString] FacturationListQuery $query = new FacturationListQuery(),
    ): JsonResponse {
        return $this->apiSuccess($structureService->listActifs($query->type), 'Structures actives récupérées avec succès.');
    }

    #[Route('/meta', name: 'api_facturation_structures_meta', methods: ['GET'])]
    #[IsGranted(FacturationPermissions::STRUCTURE_READ)]
    public function meta(): JsonResponse
    {
        return $this->apiSuccess(
            [
                'types' => Structure::getTypes(),
                'statuts' => Structure::getStatuts(),
                'categories' => CategorieTarifaire::definitions(),
            ],
            'Métadonnées structures récupérées.',
        );
    }

    #[Route('/{id}', name: 'api_facturation_structures_show', methods: ['GET'], requirements: ['id' => '\d+'])]
    #[IsGranted(FacturationPermissions::STRUCTURE_READ)]
    public function show(StructureService $structureService, int $id): JsonResponse
    {
        return $this->apiSuccess($structureService->serializeSummary($structureService->getById($id)), 'Structure récupérée avec succès.');
    }

    #[Route('', name: 'api_facturation_structures_create', methods: ['POST'])]
    #[IsGranted(FacturationPermissions::STRUCTURE_CREATE)]
    public function create(StructureService $structureService, #[MapRequestPayload] CreateStructureInput $input): JsonResponse
    {
        return $this->apiSuccess($structureService->serializeSummary($structureService->create($input)), 'Structure créée avec succès.', Response::HTTP_CREATED);
    }

    #[Route('/{id}', name: 'api_facturation_structures_update', methods: ['PUT'], requirements: ['id' => '\d+'])]
    #[IsGranted(FacturationPermissions::STRUCTURE_UPDATE)]
    public function update(StructureService $structureService, int $id, #[MapRequestPayload] UpdateStructureInput $input): JsonResponse
    {
        return $this->apiSuccess($structureService->serializeSummary($structureService->update($id, $input)), 'Structure mise à jour avec succès.');
    }

    #[Route('/{id}', name: 'api_facturation_structures_delete', methods: ['DELETE'], requirements: ['id' => '\d+'])]
    #[IsGranted(FacturationPermissions::STRUCTURE_DELETE)]
    public function delete(StructureService $structureService, int $id): JsonResponse
    {
        $structureService->delete($id);

        return $this->apiSuccess(message: 'Structure supprimée avec succès.');
    }
}
