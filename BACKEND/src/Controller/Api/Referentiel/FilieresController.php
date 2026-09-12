<?php

namespace App\Controller\Api\Referentiel;

use App\Controller\Api\Trait\JsonResponseTrait;
use App\DTO\Referentiel\CreateFiliereInput;
use App\DTO\Referentiel\ReferentielListQuery;
use App\DTO\Referentiel\UpdateFiliereInput;
use App\Security\Permission\ReferentielPermissions;
use App\Service\Referentiel\FiliereService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryString;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/v1/referentiel/filieres')]
final class FilieresController extends AbstractController
{
    use JsonResponseTrait;

    public function __construct(
        private readonly FiliereService $filiereService,
    ) {
    }

    #[Route('', name: 'api_referentiel_filieres_index', methods: ['GET'])]
    #[IsGranted(ReferentielPermissions::FILIERE_READ)]
    public function index(#[MapQueryString] ReferentielListQuery $query = new ReferentielListQuery()): JsonResponse
    {
        $result = $this->filiereService->paginate($query);

        return $this->apiPaginatedSuccess(
            $result->items,
            $result->page,
            $result->limit,
            $result->total,
            'Liste des filières récupérée avec succès.',
        );
    }

    #[Route('/{id}', name: 'api_referentiel_filieres_show', methods: ['GET'])]
    #[IsGranted(ReferentielPermissions::FILIERE_READ)]
    public function show(int $id): JsonResponse
    {
        return $this->apiSuccess(
            $this->filiereService->serializeSummary($this->filiereService->getById($id)),
            'Filière récupérée avec succès.',
        );
    }

    #[Route('', name: 'api_referentiel_filieres_create', methods: ['POST'])]
    #[IsGranted(ReferentielPermissions::FILIERE_CREATE)]
    public function create(#[MapRequestPayload] CreateFiliereInput $input): JsonResponse
    {
        return $this->apiSuccess(
            $this->filiereService->serializeSummary($this->filiereService->create($input)),
            'Filière créée avec succès.',
            Response::HTTP_CREATED,
        );
    }

    #[Route('/{id}', name: 'api_referentiel_filieres_update', methods: ['PUT'])]
    #[IsGranted(ReferentielPermissions::FILIERE_UPDATE)]
    public function update(int $id, #[MapRequestPayload] UpdateFiliereInput $input): JsonResponse
    {
        return $this->apiSuccess(
            $this->filiereService->serializeSummary($this->filiereService->update($id, $input)),
            'Filière mise à jour avec succès.',
        );
    }

    #[Route('/{id}', name: 'api_referentiel_filieres_delete', methods: ['DELETE'])]
    #[IsGranted(ReferentielPermissions::FILIERE_DELETE)]
    public function delete(int $id): JsonResponse
    {
        $this->filiereService->delete($id);

        return $this->apiSuccess(message: 'Filière supprimée avec succès.');
    }
}
