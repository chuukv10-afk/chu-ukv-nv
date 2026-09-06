<?php

namespace App\Controller\Api\Pharmacie;

use App\Controller\Api\Trait\JsonResponseTrait;
use App\DTO\Pharmacie\PharmacieListQuery;
use App\DTO\Pharmacie\UpsertReceptionInput;
use App\Security\Permission\PharmaciePermissions;
use App\Service\Pharmacie\ReceptionService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryString;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/v1/pharmacie/receptions')]
#[IsGranted('ROLE_PERSONNEL')]
final class ReceptionsController extends AbstractController
{
    use JsonResponseTrait;

    #[Route('', name: 'api_pharmacie_receptions_index', methods: ['GET'])]
    #[IsGranted(PharmaciePermissions::RECEPTION_READ)]
    public function index(
        ReceptionService $receptionService,
        #[MapQueryString] PharmacieListQuery $query = new PharmacieListQuery(),
    ): JsonResponse {
        $result = $receptionService->paginate($query);

        return $this->apiPaginatedSuccess($result->items, $result->page, $result->limit, $result->total, 'Liste des réceptions récupérée avec succès.');
    }

    #[Route('/{id}', name: 'api_pharmacie_receptions_show', methods: ['GET'], requirements: ['id' => '\d+'])]
    #[IsGranted(PharmaciePermissions::RECEPTION_READ)]
    public function show(ReceptionService $receptionService, int $id): JsonResponse
    {
        return $this->apiSuccess($receptionService->serializeDetail($receptionService->getById($id)), 'Réception récupérée avec succès.');
    }

    #[Route('', name: 'api_pharmacie_receptions_create', methods: ['POST'])]
    #[IsGranted(PharmaciePermissions::RECEPTION_CREATE)]
    public function create(ReceptionService $receptionService, #[MapRequestPayload] UpsertReceptionInput $input): JsonResponse
    {
        return $this->apiSuccess($receptionService->serializeDetail($receptionService->create($input)), 'Réception créée avec succès.', Response::HTTP_CREATED);
    }

    #[Route('/{id}', name: 'api_pharmacie_receptions_update', methods: ['PUT'], requirements: ['id' => '\d+'])]
    #[IsGranted(PharmaciePermissions::RECEPTION_UPDATE)]
    public function update(ReceptionService $receptionService, int $id, #[MapRequestPayload] UpsertReceptionInput $input): JsonResponse
    {
        return $this->apiSuccess($receptionService->serializeDetail($receptionService->update($id, $input)), 'Réception mise à jour avec succès.');
    }

    #[Route('/{id}/valider', name: 'api_pharmacie_receptions_valider', methods: ['POST'], requirements: ['id' => '\d+'])]
    #[IsGranted(PharmaciePermissions::RECEPTION_VALIDER)]
    public function valider(ReceptionService $receptionService, int $id): JsonResponse
    {
        return $this->apiSuccess($receptionService->serializeDetail($receptionService->valider($id)), 'Réception validée, stock mis à jour.');
    }

    #[Route('/{id}', name: 'api_pharmacie_receptions_delete', methods: ['DELETE'], requirements: ['id' => '\d+'])]
    #[IsGranted(PharmaciePermissions::RECEPTION_DELETE)]
    public function delete(ReceptionService $receptionService, int $id): JsonResponse
    {
        $receptionService->delete($id);

        return $this->apiSuccess(message: 'Réception supprimée avec succès.');
    }
}
