<?php

namespace App\Controller\Api\Organisation;

use App\Controller\Api\Trait\JsonResponseTrait;
use App\DTO\Organisation\CreateDepartementInput;
use App\DTO\Organisation\UpdateDepartementInput;
use App\Entity\Departement;
use App\Security\Permission\OrganisationPermissions;
use App\Service\Organisation\DepartementService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/v1/organisation/departements')]
final class DepartementsController extends AbstractController
{
    use JsonResponseTrait;

    public function __construct(
        private readonly DepartementService $departementService,
    ) {
    }

    #[Route('', name: 'api_organisation_departements_index', methods: ['GET'])]
    #[IsGranted(OrganisationPermissions::DEPARTEMENT_READ)]
    public function index(): JsonResponse
    {
        return $this->apiSuccess(
            array_map([$this, 'serialize'], $this->departementService->findAll()),
            'Liste des départements récupérée avec succès.',
        );
    }

    #[Route('/{id}', name: 'api_organisation_departements_show', methods: ['GET'])]
    #[IsGranted(OrganisationPermissions::DEPARTEMENT_READ)]
    public function show(int $id): JsonResponse
    {
        $departement = $this->departementService->getById($id);
        $this->denyAccessUnlessGranted(OrganisationPermissions::DEPARTEMENT_READ, $departement);

        return $this->apiSuccess(
            $this->serialize($departement),
            'Département récupéré avec succès.',
        );
    }

    #[Route('', name: 'api_organisation_departements_create', methods: ['POST'])]
    #[IsGranted(OrganisationPermissions::DEPARTEMENT_CREATE)]
    public function create(#[MapRequestPayload] CreateDepartementInput $input): JsonResponse
    {
        return $this->apiSuccess(
            $this->serialize($this->departementService->create($input)),
            'Département créé avec succès.',
            Response::HTTP_CREATED,
        );
    }

    #[Route('/{id}', name: 'api_organisation_departements_update', methods: ['PUT'])]
    #[IsGranted(OrganisationPermissions::DEPARTEMENT_UPDATE)]
    public function update(int $id, #[MapRequestPayload] UpdateDepartementInput $input): JsonResponse
    {
        $departement = $this->departementService->getById($id);
        $this->denyAccessUnlessGranted(OrganisationPermissions::DEPARTEMENT_UPDATE, $departement);

        return $this->apiSuccess(
            $this->serialize($this->departementService->update($id, $input)),
            'Département mis à jour avec succès.',
        );
    }

    #[Route('/{id}', name: 'api_organisation_departements_delete', methods: ['DELETE'])]
    #[IsGranted(OrganisationPermissions::DEPARTEMENT_DELETE)]
    public function delete(int $id): JsonResponse
    {
        $departement = $this->departementService->getById($id);
        $this->denyAccessUnlessGranted(OrganisationPermissions::DEPARTEMENT_DELETE, $departement);

        $this->departementService->delete($id);

        return $this->apiSuccess(message: 'Département supprimé avec succès.');
    }

    private function serialize(Departement $departement): array
    {
        return [
            'id' => $departement->getId(),
            'code' => $departement->getCode(),
            'libelle' => $departement->getLibelle(),
            'type' => $departement->getType(),
            'createdAt' => $departement->getCreatedAt()?->format(\DateTimeInterface::ATOM),
        ];
    }
}
