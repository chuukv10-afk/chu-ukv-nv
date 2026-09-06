<?php

namespace App\Controller\Api\Pharmacie;

use App\Controller\Api\Trait\JsonResponseTrait;
use App\DTO\Pharmacie\CreateUniteMedicamentInput;
use App\DTO\Pharmacie\UpdateUniteMedicamentInput;
use App\DTO\Referentiel\ReferentielListQuery;
use App\Entity\UniteMedicament;
use App\Security\Permission\PharmaciePermissions;
use App\Service\Pharmacie\UniteMedicamentService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryString;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/v1/pharmacie/unites')]
#[IsGranted('ROLE_PERSONNEL')]
final class UnitesController extends AbstractController
{
    use JsonResponseTrait;

    #[Route('', name: 'api_pharmacie_unites_index', methods: ['GET'])]
    #[IsGranted(PharmaciePermissions::UNITE_READ)]
    public function index(
        UniteMedicamentService $uniteMedicamentService,
        #[MapQueryString] ReferentielListQuery $query = new ReferentielListQuery(),
    ): JsonResponse {
        $result = $uniteMedicamentService->paginate($query);

        return $this->apiPaginatedSuccess(
            $result->items,
            $result->page,
            $result->limit,
            $result->total,
            'Liste des unités récupérée avec succès.',
        );
    }

    #[Route('/actifs', name: 'api_pharmacie_unites_actifs', methods: ['GET'])]
    public function actifs(UniteMedicamentService $uniteMedicamentService): JsonResponse
    {
        return $this->apiSuccess(
            $uniteMedicamentService->listActifs(),
            'Unités actives récupérées avec succès.',
        );
    }

    #[Route('/meta', name: 'api_pharmacie_unites_meta', methods: ['GET'])]
    #[IsGranted(PharmaciePermissions::UNITE_READ)]
    public function meta(): JsonResponse
    {
        return $this->apiSuccess(
            ['statuts' => UniteMedicament::getStatuts()],
            'Métadonnées unités récupérées avec succès.',
        );
    }

    #[Route('/{id}', name: 'api_pharmacie_unites_show', methods: ['GET'], requirements: ['id' => '\d+'])]
    #[IsGranted(PharmaciePermissions::UNITE_READ)]
    public function show(UniteMedicamentService $uniteMedicamentService, int $id): JsonResponse
    {
        return $this->apiSuccess(
            $uniteMedicamentService->serializeSummary($uniteMedicamentService->getById($id)),
            'Unité récupérée avec succès.',
        );
    }

    #[Route('', name: 'api_pharmacie_unites_create', methods: ['POST'])]
    #[IsGranted(PharmaciePermissions::UNITE_CREATE)]
    public function create(
        UniteMedicamentService $uniteMedicamentService,
        #[MapRequestPayload] CreateUniteMedicamentInput $input,
    ): JsonResponse {
        return $this->apiSuccess(
            $uniteMedicamentService->serializeSummary($uniteMedicamentService->create($input)),
            'Unité créée avec succès.',
            Response::HTTP_CREATED,
        );
    }

    #[Route('/{id}', name: 'api_pharmacie_unites_update', methods: ['PUT'], requirements: ['id' => '\d+'])]
    #[IsGranted(PharmaciePermissions::UNITE_UPDATE)]
    public function update(
        UniteMedicamentService $uniteMedicamentService,
        int $id,
        #[MapRequestPayload] UpdateUniteMedicamentInput $input,
    ): JsonResponse {
        return $this->apiSuccess(
            $uniteMedicamentService->serializeSummary($uniteMedicamentService->update($id, $input)),
            'Unité mise à jour avec succès.',
        );
    }

    #[Route('/{id}', name: 'api_pharmacie_unites_delete', methods: ['DELETE'], requirements: ['id' => '\d+'])]
    #[IsGranted(PharmaciePermissions::UNITE_DELETE)]
    public function delete(UniteMedicamentService $uniteMedicamentService, int $id): JsonResponse
    {
        $uniteMedicamentService->delete($id);

        return $this->apiSuccess(message: 'Unité supprimée avec succès.');
    }
}
