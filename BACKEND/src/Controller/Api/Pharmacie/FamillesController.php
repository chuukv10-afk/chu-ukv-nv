<?php

namespace App\Controller\Api\Pharmacie;

use App\Controller\Api\Trait\JsonResponseTrait;
use App\DTO\Pharmacie\CreateFamilleMedicamentInput;
use App\DTO\Pharmacie\UpdateFamilleMedicamentInput;
use App\DTO\Referentiel\ReferentielListQuery;
use App\Entity\FamilleMedicament;
use App\Security\Permission\PharmaciePermissions;
use App\Service\Pharmacie\FamilleMedicamentService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryString;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/v1/pharmacie/familles')]
#[IsGranted('ROLE_PERSONNEL')]
final class FamillesController extends AbstractController
{
    use JsonResponseTrait;

    #[Route('', name: 'api_pharmacie_familles_index', methods: ['GET'])]
    #[IsGranted(PharmaciePermissions::FAMILLE_READ)]
    public function index(
        FamilleMedicamentService $familleMedicamentService,
        #[MapQueryString] ReferentielListQuery $query = new ReferentielListQuery(),
    ): JsonResponse {
        $result = $familleMedicamentService->paginate($query);

        return $this->apiPaginatedSuccess(
            $result->items,
            $result->page,
            $result->limit,
            $result->total,
            'Liste des familles récupérée avec succès.',
        );
    }

    #[Route('/actifs', name: 'api_pharmacie_familles_actifs', methods: ['GET'])]
    public function actifs(FamilleMedicamentService $familleMedicamentService): JsonResponse
    {
        return $this->apiSuccess(
            $familleMedicamentService->listActifs(),
            'Familles actives récupérées avec succès.',
        );
    }

    #[Route('/meta', name: 'api_pharmacie_familles_meta', methods: ['GET'])]
    #[IsGranted(PharmaciePermissions::FAMILLE_READ)]
    public function meta(): JsonResponse
    {
        return $this->apiSuccess(
            ['statuts' => FamilleMedicament::getStatuts()],
            'Métadonnées familles récupérées avec succès.',
        );
    }

    #[Route('/{id}', name: 'api_pharmacie_familles_show', methods: ['GET'], requirements: ['id' => '\d+'])]
    #[IsGranted(PharmaciePermissions::FAMILLE_READ)]
    public function show(FamilleMedicamentService $familleMedicamentService, int $id): JsonResponse
    {
        return $this->apiSuccess(
            $familleMedicamentService->serializeSummary($familleMedicamentService->getById($id)),
            'Famille récupérée avec succès.',
        );
    }

    #[Route('', name: 'api_pharmacie_familles_create', methods: ['POST'])]
    #[IsGranted(PharmaciePermissions::FAMILLE_CREATE)]
    public function create(
        FamilleMedicamentService $familleMedicamentService,
        #[MapRequestPayload] CreateFamilleMedicamentInput $input,
    ): JsonResponse {
        return $this->apiSuccess(
            $familleMedicamentService->serializeSummary($familleMedicamentService->create($input)),
            'Famille créée avec succès.',
            Response::HTTP_CREATED,
        );
    }

    #[Route('/{id}', name: 'api_pharmacie_familles_update', methods: ['PUT'], requirements: ['id' => '\d+'])]
    #[IsGranted(PharmaciePermissions::FAMILLE_UPDATE)]
    public function update(
        FamilleMedicamentService $familleMedicamentService,
        int $id,
        #[MapRequestPayload] UpdateFamilleMedicamentInput $input,
    ): JsonResponse {
        return $this->apiSuccess(
            $familleMedicamentService->serializeSummary($familleMedicamentService->update($id, $input)),
            'Famille mise à jour avec succès.',
        );
    }

    #[Route('/{id}', name: 'api_pharmacie_familles_delete', methods: ['DELETE'], requirements: ['id' => '\d+'])]
    #[IsGranted(PharmaciePermissions::FAMILLE_DELETE)]
    public function delete(FamilleMedicamentService $familleMedicamentService, int $id): JsonResponse
    {
        $familleMedicamentService->delete($id);

        return $this->apiSuccess(message: 'Famille supprimée avec succès.');
    }
}
