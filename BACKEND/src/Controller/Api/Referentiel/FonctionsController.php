<?php

namespace App\Controller\Api\Referentiel;

use App\Controller\Api\Trait\JsonResponseTrait;
use App\DTO\Referentiel\CreateFonctionInput;
use App\DTO\Referentiel\ReferentielListQuery;
use App\DTO\Referentiel\UpdateFonctionInput;
use App\Security\Permission\ReferentielPermissions;
use App\Service\Referentiel\FonctionService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryString;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/v1/referentiel/fonctions')]
final class FonctionsController extends AbstractController
{
    use JsonResponseTrait;

    public function __construct(
        private readonly FonctionService $fonctionService,
    ) {
    }

    #[Route('', name: 'api_referentiel_fonctions_index', methods: ['GET'])]
    #[IsGranted(ReferentielPermissions::FONCTION_READ)]
    public function index(#[MapQueryString] ReferentielListQuery $query = new ReferentielListQuery()): JsonResponse
    {
        $result = $this->fonctionService->paginate($query);

        return $this->apiPaginatedSuccess(
            $result->items,
            $result->page,
            $result->limit,
            $result->total,
            'Liste des fonctions récupérée avec succès.',
        );
    }

    #[Route('/{id}', name: 'api_referentiel_fonctions_show', methods: ['GET'])]
    #[IsGranted(ReferentielPermissions::FONCTION_READ)]
    public function show(int $id): JsonResponse
    {
        return $this->apiSuccess(
            $this->fonctionService->serializeSummary($this->fonctionService->getById($id)),
            'Fonction récupérée avec succès.',
        );
    }

    #[Route('', name: 'api_referentiel_fonctions_create', methods: ['POST'])]
    #[IsGranted(ReferentielPermissions::FONCTION_CREATE)]
    public function create(#[MapRequestPayload] CreateFonctionInput $input): JsonResponse
    {
        return $this->apiSuccess(
            $this->fonctionService->serializeSummary($this->fonctionService->create($input)),
            'Fonction créée avec succès.',
            Response::HTTP_CREATED,
        );
    }

    #[Route('/{id}', name: 'api_referentiel_fonctions_update', methods: ['PUT'])]
    #[IsGranted(ReferentielPermissions::FONCTION_UPDATE)]
    public function update(int $id, #[MapRequestPayload] UpdateFonctionInput $input): JsonResponse
    {
        return $this->apiSuccess(
            $this->fonctionService->serializeSummary($this->fonctionService->update($id, $input)),
            'Fonction mise à jour avec succès.',
        );
    }

    #[Route('/{id}', name: 'api_referentiel_fonctions_delete', methods: ['DELETE'])]
    #[IsGranted(ReferentielPermissions::FONCTION_DELETE)]
    public function delete(int $id): JsonResponse
    {
        $this->fonctionService->delete($id);

        return $this->apiSuccess(message: 'Fonction supprimée avec succès.');
    }
}
