<?php

namespace App\Controller\Api\Referentiel;

use App\Controller\Api\Trait\JsonResponseTrait;
use App\DTO\Referentiel\CreateSpecialiteInput;
use App\DTO\Referentiel\UpdateSpecialiteInput;
use App\Entity\Specialite;
use App\Security\Permission\ReferentielPermissions;
use App\Service\Referentiel\SpecialiteService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/v1/referentiel/specialites')]
final class SpecialitesController extends AbstractController
{
    use JsonResponseTrait;

    public function __construct(
        private readonly SpecialiteService $specialiteService,
    ) {
    }

    #[Route('', name: 'api_referentiel_specialites_index', methods: ['GET'])]
    #[IsGranted(ReferentielPermissions::SPECIALITE_READ)]
    public function index(): JsonResponse
    {
        return $this->apiSuccess(
            array_map([$this, 'serialize'], $this->specialiteService->findAll()),
            'Liste des spécialités récupérée avec succès.',
        );
    }

    #[Route('/{id}', name: 'api_referentiel_specialites_show', methods: ['GET'])]
    #[IsGranted(ReferentielPermissions::SPECIALITE_READ)]
    public function show(int $id): JsonResponse
    {
        $specialite = $this->specialiteService->getById($id);
        $this->denyAccessUnlessGranted(ReferentielPermissions::SPECIALITE_READ, $specialite);

        return $this->apiSuccess(
            $this->serialize($specialite),
            'Spécialité récupérée avec succès.',
        );
    }

    #[Route('', name: 'api_referentiel_specialites_create', methods: ['POST'])]
    #[IsGranted(ReferentielPermissions::SPECIALITE_CREATE)]
    public function create(#[MapRequestPayload] CreateSpecialiteInput $input): JsonResponse
    {
        return $this->apiSuccess(
            $this->serialize($this->specialiteService->create($input)),
            'Spécialité créée avec succès.',
            Response::HTTP_CREATED,
        );
    }

    #[Route('/{id}', name: 'api_referentiel_specialites_update', methods: ['PUT'])]
    #[IsGranted(ReferentielPermissions::SPECIALITE_UPDATE)]
    public function update(int $id, #[MapRequestPayload] UpdateSpecialiteInput $input): JsonResponse
    {
        $specialite = $this->specialiteService->getById($id);
        $this->denyAccessUnlessGranted(ReferentielPermissions::SPECIALITE_UPDATE, $specialite);

        return $this->apiSuccess(
            $this->serialize($this->specialiteService->update($id, $input)),
            'Spécialité mise à jour avec succès.',
        );
    }

    #[Route('/{id}', name: 'api_referentiel_specialites_delete', methods: ['DELETE'])]
    #[IsGranted(ReferentielPermissions::SPECIALITE_DELETE)]
    public function delete(int $id): JsonResponse
    {
        $specialite = $this->specialiteService->getById($id);
        $this->denyAccessUnlessGranted(ReferentielPermissions::SPECIALITE_DELETE, $specialite);

        $this->specialiteService->delete($id);

        return $this->apiSuccess(message: 'Spécialité supprimée avec succès.');
    }

    private function serialize(Specialite $specialite): array
    {
        return [
            'id' => $specialite->getId(),
            'code' => $specialite->getCode(),
            'libelle' => $specialite->getLibelle(),
            'createdAt' => $specialite->getCreatedAt()?->format(\DateTimeInterface::ATOM),
        ];
    }
}
