<?php

namespace App\Controller\Api\Organisation;

use App\Controller\Api\Trait\JsonResponseTrait;
use App\DTO\Organisation\CreateChambreInput;
use App\DTO\Organisation\UpdateChambreInput;
use App\Entity\Chambre;
use App\Security\Permission\OrganisationPermissions;
use App\Service\Organisation\ChambreService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/v1/organisation/chambres')]
final class ChambresController extends AbstractController
{
    use JsonResponseTrait;

    public function __construct(
        private readonly ChambreService $chambreService,
    ) {
    }

    #[Route('', name: 'api_organisation_chambres_index', methods: ['GET'])]
    #[IsGranted(OrganisationPermissions::CHAMBRE_READ)]
    public function index(): JsonResponse
    {
        return $this->apiSuccess(
            array_map([$this, 'serialize'], $this->chambreService->findAll()),
            'Liste des chambres récupérée avec succès.',
        );
    }

    #[Route('/{id}', name: 'api_organisation_chambres_show', methods: ['GET'])]
    #[IsGranted(OrganisationPermissions::CHAMBRE_READ)]
    public function show(int $id): JsonResponse
    {
        $chambre = $this->chambreService->getById($id);
        $this->denyAccessUnlessGranted(OrganisationPermissions::CHAMBRE_READ, $chambre);

        return $this->apiSuccess(
            $this->serialize($chambre),
            'Chambre récupérée avec succès.',
        );
    }

    #[Route('', name: 'api_organisation_chambres_create', methods: ['POST'])]
    #[IsGranted(OrganisationPermissions::CHAMBRE_CREATE)]
    public function create(#[MapRequestPayload] CreateChambreInput $input): JsonResponse
    {
        return $this->apiSuccess(
            $this->serialize($this->chambreService->create($input)),
            'Chambre créée avec succès.',
            Response::HTTP_CREATED,
        );
    }

    #[Route('/{id}', name: 'api_organisation_chambres_update', methods: ['PUT'])]
    #[IsGranted(OrganisationPermissions::CHAMBRE_UPDATE)]
    public function update(int $id, #[MapRequestPayload] UpdateChambreInput $input): JsonResponse
    {
        $chambre = $this->chambreService->getById($id);
        $this->denyAccessUnlessGranted(OrganisationPermissions::CHAMBRE_UPDATE, $chambre);

        return $this->apiSuccess(
            $this->serialize($this->chambreService->update($id, $input)),
            'Chambre mise à jour avec succès.',
        );
    }

    #[Route('/{id}', name: 'api_organisation_chambres_delete', methods: ['DELETE'])]
    #[IsGranted(OrganisationPermissions::CHAMBRE_DELETE)]
    public function delete(int $id): JsonResponse
    {
        $chambre = $this->chambreService->getById($id);
        $this->denyAccessUnlessGranted(OrganisationPermissions::CHAMBRE_DELETE, $chambre);

        $this->chambreService->delete($id);

        return $this->apiSuccess(message: 'Chambre supprimée avec succès.');
    }

    private function serialize(Chambre $chambre): array
    {
        return [
            'id' => $chambre->getId(),
            'code' => $chambre->getCode(),
            'libelle' => $chambre->getLibelle(),
            'type' => $chambre->getType(),
            'createdAt' => $chambre->getCreatedAt()?->format(\DateTimeInterface::ATOM),
        ];
    }
}
