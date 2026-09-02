<?php

namespace App\Controller\Api\Organisation;

use App\Controller\Api\Trait\JsonResponseTrait;
use App\DTO\Organisation\CreateBlocInput;
use App\DTO\Organisation\UpdateBlocInput;
use App\Entity\Bloc;
use App\Security\Permission\OrganisationPermissions;
use App\Service\Organisation\BlocService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/v1/organisation/blocs')]
final class BlocsController extends AbstractController
{
    use JsonResponseTrait;

    public function __construct(
        private readonly BlocService $blocService,
    ) {
    }

    #[Route('', name: 'api_organisation_blocs_index', methods: ['GET'])]
    #[IsGranted(OrganisationPermissions::BLOC_READ)]
    public function index(): JsonResponse
    {
        return $this->apiSuccess(
            array_map([$this, 'serialize'], $this->blocService->findAll()),
            'Liste des blocs récupérée avec succès.',
        );
    }

    #[Route('/{id}', name: 'api_organisation_blocs_show', methods: ['GET'])]
    #[IsGranted(OrganisationPermissions::BLOC_READ)]
    public function show(int $id): JsonResponse
    {
        $bloc = $this->blocService->getById($id);
        $this->denyAccessUnlessGranted(OrganisationPermissions::BLOC_READ, $bloc);

        return $this->apiSuccess(
            $this->serialize($bloc),
            'Bloc récupéré avec succès.',
        );
    }

    #[Route('', name: 'api_organisation_blocs_create', methods: ['POST'])]
    #[IsGranted(OrganisationPermissions::BLOC_CREATE)]
    public function create(#[MapRequestPayload] CreateBlocInput $input): JsonResponse
    {
        return $this->apiSuccess(
            $this->serialize($this->blocService->create($input)),
            'Bloc créé avec succès.',
            Response::HTTP_CREATED,
        );
    }

    #[Route('/{id}', name: 'api_organisation_blocs_update', methods: ['PUT'])]
    #[IsGranted(OrganisationPermissions::BLOC_UPDATE)]
    public function update(int $id, #[MapRequestPayload] UpdateBlocInput $input): JsonResponse
    {
        $bloc = $this->blocService->getById($id);
        $this->denyAccessUnlessGranted(OrganisationPermissions::BLOC_UPDATE, $bloc);

        return $this->apiSuccess(
            $this->serialize($this->blocService->update($id, $input)),
            'Bloc mis à jour avec succès.',
        );
    }

    #[Route('/{id}', name: 'api_organisation_blocs_delete', methods: ['DELETE'])]
    #[IsGranted(OrganisationPermissions::BLOC_DELETE)]
    public function delete(int $id): JsonResponse
    {
        $bloc = $this->blocService->getById($id);
        $this->denyAccessUnlessGranted(OrganisationPermissions::BLOC_DELETE, $bloc);

        $this->blocService->delete($id);

        return $this->apiSuccess(message: 'Bloc supprimé avec succès.');
    }

    private function serialize(Bloc $bloc): array
    {
        return [
            'id' => $bloc->getId(),
            'code' => $bloc->getCode(),
            'libelle' => $bloc->getLibelle(),
            'chambre' => $bloc->getChambre(),
            'createdAt' => $bloc->getCreatedAt()?->format(\DateTimeInterface::ATOM),
        ];
    }
}
