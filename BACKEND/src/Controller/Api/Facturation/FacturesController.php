<?php

namespace App\Controller\Api\Facturation;

use App\Controller\Api\Trait\JsonResponseTrait;
use App\DTO\Facturation\FacturationListQuery;
use App\DTO\Facturation\FactureListQuery;
use App\DTO\Facturation\UpsertFactureInput;
use App\Entity\ActeFinancier;
use App\Security\Permission\FacturationPermissions;
use App\Service\Facturation\ActeFinancierService;
use App\Service\Facturation\FactureService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryString;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/v1/facturation/factures')]
#[IsGranted('ROLE_PERSONNEL')]
final class FacturesController extends AbstractController
{
    use JsonResponseTrait;

    #[Route('', name: 'api_facturation_factures_index', methods: ['GET'])]
    #[IsGranted(FacturationPermissions::FACTURE_READ)]
    public function index(
        FactureService $factureService,
        #[MapQueryString] FactureListQuery $query = new FactureListQuery(),
    ): JsonResponse {
        $result = $factureService->paginate($query);

        return $this->apiPaginatedSuccess(
            $result->items,
            $result->page,
            $result->limit,
            $result->total,
            'Liste des factures récupérée avec succès.',
        );
    }

    #[Route('/actes', name: 'api_facturation_factures_actes', methods: ['GET'])]
    public function actes(
        ActeFinancierService $acteFinancierService,
        #[MapQueryString] FacturationListQuery $query = new FacturationListQuery(),
    ): JsonResponse {
        if (
            !$this->isGranted(FacturationPermissions::FACTURE_READ)
            && !$this->isGranted(FacturationPermissions::FACTURE_CREATE)
            && !$this->isGranted(FacturationPermissions::FACTURE_UPDATE)
            && !$this->isGranted(FacturationPermissions::ACTE_READ)
        ) {
            throw $this->createAccessDeniedException();
        }

        $query->statut = $query->statut ?: ActeFinancier::STATUT_ACTIF;
        $result = $acteFinancierService->paginate($query);

        return $this->apiPaginatedSuccess(
            $result->items,
            $result->page,
            $result->limit,
            $result->total,
            'Actes de la grille récupérés avec succès.',
        );
    }

    #[Route('', name: 'api_facturation_factures_create', methods: ['POST'])]
    #[IsGranted(FacturationPermissions::FACTURE_CREATE)]
    public function create(
        FactureService $factureService,
        #[MapRequestPayload] UpsertFactureInput $input,
    ): JsonResponse {
        return $this->apiSuccess(
            $factureService->serializeDetail($factureService->create($input)),
            'Facture créée avec succès.',
            Response::HTTP_CREATED,
        );
    }

    #[Route('/{id}', name: 'api_facturation_factures_show', methods: ['GET'], requirements: ['id' => '\d+'])]
    #[IsGranted(FacturationPermissions::FACTURE_READ)]
    public function show(FactureService $factureService, int $id): JsonResponse
    {
        return $this->apiSuccess(
            $factureService->serializeDetail($factureService->getById($id)),
            'Facture récupérée avec succès.',
        );
    }

    #[Route('/{id}', name: 'api_facturation_factures_update', methods: ['PUT'], requirements: ['id' => '\d+'])]
    #[IsGranted(FacturationPermissions::FACTURE_UPDATE)]
    public function update(
        FactureService $factureService,
        int $id,
        #[MapRequestPayload] UpsertFactureInput $input,
    ): JsonResponse {
        return $this->apiSuccess(
            $factureService->serializeDetail($factureService->update($id, $input)),
            'Facture mise à jour avec succès.',
        );
    }

    #[Route('/{id}/valider', name: 'api_facturation_factures_valider', methods: ['POST'], requirements: ['id' => '\d+'])]
    #[IsGranted(FacturationPermissions::FACTURE_VALIDER)]
    public function valider(FactureService $factureService, int $id): JsonResponse
    {
        return $this->apiSuccess(
            $factureService->serializeDetail($factureService->valider($id)),
            'Facture validée.',
        );
    }

    #[Route('/{id}/annuler', name: 'api_facturation_factures_annuler', methods: ['POST'], requirements: ['id' => '\d+'])]
    #[IsGranted(FacturationPermissions::FACTURE_UPDATE)]
    public function annuler(FactureService $factureService, int $id): JsonResponse
    {
        return $this->apiSuccess(
            $factureService->serializeDetail($factureService->annuler($id)),
            'Facture annulée.',
        );
    }

    #[Route('/{id}', name: 'api_facturation_factures_delete', methods: ['DELETE'], requirements: ['id' => '\d+'])]
    #[IsGranted(FacturationPermissions::FACTURE_DELETE)]
    public function delete(FactureService $factureService, int $id): JsonResponse
    {
        $factureService->delete($id);

        return $this->apiSuccess(message: 'Facture supprimée avec succès.');
    }
}
