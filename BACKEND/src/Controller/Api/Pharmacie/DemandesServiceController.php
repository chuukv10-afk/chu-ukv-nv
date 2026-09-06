<?php

namespace App\Controller\Api\Pharmacie;

use App\Controller\Api\Trait\JsonResponseTrait;
use App\DTO\Pharmacie\PharmacieListQuery;
use App\DTO\Pharmacie\RefuserDemandeInput;
use App\DTO\Pharmacie\ReglerDemandeInput;
use App\DTO\Pharmacie\UpsertDemandeServiceInput;
use App\Security\Permission\PharmaciePermissions;
use App\Service\Pharmacie\DemandeServiceService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryString;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/v1/pharmacie/demandes-service')]
#[IsGranted('ROLE_PERSONNEL')]
final class DemandesServiceController extends AbstractController
{
    use JsonResponseTrait;

    #[Route('', name: 'api_pharmacie_demandes_service_index', methods: ['GET'])]
    #[IsGranted(PharmaciePermissions::DEMANDE_SERVICE_READ)]
    public function index(
        DemandeServiceService $demandeServiceService,
        #[MapQueryString] PharmacieListQuery $query = new PharmacieListQuery(),
    ): JsonResponse {
        $result = $demandeServiceService->paginate($query);

        return $this->apiPaginatedSuccess($result->items, $result->page, $result->limit, $result->total, 'Liste des demandes de service récupérée avec succès.');
    }

    #[Route('/{id}', name: 'api_pharmacie_demandes_service_show', methods: ['GET'], requirements: ['id' => '\d+'])]
    #[IsGranted(PharmaciePermissions::DEMANDE_SERVICE_READ)]
    public function show(DemandeServiceService $demandeServiceService, int $id): JsonResponse
    {
        return $this->apiSuccess($demandeServiceService->serializeDetail($demandeServiceService->getById($id)), 'Demande récupérée avec succès.');
    }

    #[Route('', name: 'api_pharmacie_demandes_service_create', methods: ['POST'])]
    #[IsGranted(PharmaciePermissions::DEMANDE_SERVICE_CREATE)]
    public function create(DemandeServiceService $demandeServiceService, #[MapRequestPayload] UpsertDemandeServiceInput $input): JsonResponse
    {
        return $this->apiSuccess($demandeServiceService->serializeDetail($demandeServiceService->create($input)), 'Demande créée avec succès.', Response::HTTP_CREATED);
    }

    #[Route('/{id}', name: 'api_pharmacie_demandes_service_update', methods: ['PUT'], requirements: ['id' => '\d+'])]
    #[IsGranted(PharmaciePermissions::DEMANDE_SERVICE_UPDATE)]
    public function update(DemandeServiceService $demandeServiceService, int $id, #[MapRequestPayload] UpsertDemandeServiceInput $input): JsonResponse
    {
        return $this->apiSuccess($demandeServiceService->serializeDetail($demandeServiceService->update($id, $input)), 'Demande mise à jour avec succès.');
    }

    #[Route('/{id}/envoyer', name: 'api_pharmacie_demandes_service_envoyer', methods: ['POST'], requirements: ['id' => '\d+'])]
    #[IsGranted(PharmaciePermissions::DEMANDE_SERVICE_ENVOYER)]
    public function envoyer(DemandeServiceService $demandeServiceService, int $id): JsonResponse
    {
        return $this->apiSuccess($demandeServiceService->serializeDetail($demandeServiceService->envoyer($id)), 'Demande envoyée.');
    }

    #[Route('/{id}/delivrer', name: 'api_pharmacie_demandes_service_delivrer', methods: ['POST'], requirements: ['id' => '\d+'])]
    #[IsGranted(PharmaciePermissions::DEMANDE_SERVICE_DELIVRER)]
    public function delivrer(DemandeServiceService $demandeServiceService, int $id): JsonResponse
    {
        return $this->apiSuccess($demandeServiceService->serializeDetail($demandeServiceService->delivrer($id)), 'Demande délivrée, créance ouverte.');
    }

    #[Route('/{id}/refuser', name: 'api_pharmacie_demandes_service_refuser', methods: ['POST'], requirements: ['id' => '\d+'])]
    #[IsGranted(PharmaciePermissions::DEMANDE_SERVICE_REFUSER)]
    public function refuser(DemandeServiceService $demandeServiceService, int $id, #[MapRequestPayload] RefuserDemandeInput $input): JsonResponse
    {
        return $this->apiSuccess($demandeServiceService->serializeDetail($demandeServiceService->refuser($id, $input)), 'Demande refusée.');
    }

    #[Route('/{id}/regler', name: 'api_pharmacie_demandes_service_regler', methods: ['POST'], requirements: ['id' => '\d+'])]
    #[IsGranted(PharmaciePermissions::DEMANDE_SERVICE_REGLER)]
    public function regler(DemandeServiceService $demandeServiceService, int $id, #[MapRequestPayload] ReglerDemandeInput $input): JsonResponse
    {
        return $this->apiSuccess($demandeServiceService->serializeDetail($demandeServiceService->regler($id, $input)), 'Créance réglée.');
    }

    #[Route('/{id}', name: 'api_pharmacie_demandes_service_delete', methods: ['DELETE'], requirements: ['id' => '\d+'])]
    #[IsGranted(PharmaciePermissions::DEMANDE_SERVICE_DELETE)]
    public function delete(DemandeServiceService $demandeServiceService, int $id): JsonResponse
    {
        $demandeServiceService->delete($id);

        return $this->apiSuccess(message: 'Brouillon de demande supprimé.');
    }
}
