<?php

namespace App\Controller\Api\Organisation;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use App\Service\Organisation\DepartementService;

use App\DTO\Organisation\CreateDepartementInput;
use App\DTO\Organisation\UpdateDepartementInput;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;

#[Route('/api/v1/organisation/departements')]
#[IsGranted('ROLE_PERSONNEL')]
final class DepartementsController extends AbstractController
{

    public function __construct(
        private readonly DepartementService $departementService,
    ) {}

    #[Route('', name: 'api_organisation_departements_index', methods: ['GET'])]
    public function index(): JsonResponse
    {
        return $this->json([
            'data' => $this->departementService->findAll(),
            'message' => 'Liste des départements récupérée avec succès.',
        ]);
    }

    #[Route('', name: 'api_organisation_departements_create', methods: ['POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function create(
        #[MapRequestPayload] CreateDepartementInput $input,
    ): JsonResponse {
        $departement = $this->departementService->create($input);
        return $this->json([
            'data' => [
                'id' => $departement->getId(),
                'code' => $departement->getCode(),
                'libelle' => $departement->getLibelle(),
                'type' => $departement->getType(),
                'createdAt' => $departement->getCreatedAt()?->format(\DateTimeInterface::ATOM),
            ],
            'message' => 'Département créé avec succès.',
        ], Response::HTTP_CREATED);
    }

    #[Route('/{id}', name: 'api_organisation_departements_update', methods: ['PUT'])]
    #[IsGranted('ROLE_ADMIN')]
    public function update(
        int $id,
        #[MapRequestPayload] UpdateDepartementInput $input,
    ): JsonResponse {
        $departement = $this->departementService->update($id,$input);
        return $this->json([
            'data' => $departement,
            'message' => 'Département mis à jour avec succès.',
        ]);
    }
 
}
