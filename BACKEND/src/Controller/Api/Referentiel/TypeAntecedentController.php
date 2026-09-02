<?php

namespace App\Controller\Api\Referentiel;

use App\Controller\Api\Trait\JsonResponseTrait;
use App\DTO\Referentiel\CreateTypeAntecedentInput;
use App\DTO\Referentiel\UpdateTypeAntecedentInput;
use App\Entity\TypeAntecedent;
use App\Security\Permission\ReferentielPermissions;
use App\Service\Referentiel\TypeAntecedentService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/v1/referentiel/types-antecedent')]
final class TypeAntecedentController extends AbstractController
{
    use JsonResponseTrait;

    public function __construct(
        private readonly TypeAntecedentService $typeAntecedentService,
    ) {
    }

    #[Route('', name: 'api_referentiel_types_antecedent_index', methods: ['GET'])]
    #[IsGranted(ReferentielPermissions::TYPE_ANTECEDENT_READ)]
    public function index(): JsonResponse
    {
        return $this->apiSuccess(
            array_map([$this, 'serialize'], $this->typeAntecedentService->findAll()),
            'Liste des types d\'antécédent récupérée avec succès.',
        );
    }

    #[Route('/{id}', name: 'api_referentiel_types_antecedent_show', methods: ['GET'])]
    #[IsGranted(ReferentielPermissions::TYPE_ANTECEDENT_READ)]
    public function show(int $id): JsonResponse
    {
        $typeAntecedent = $this->typeAntecedentService->getById($id);
        $this->denyAccessUnlessGranted(ReferentielPermissions::TYPE_ANTECEDENT_READ, $typeAntecedent);

        return $this->apiSuccess(
            $this->serialize($typeAntecedent),
            'Type d\'antécédent récupéré avec succès.',
        );
    }

    #[Route('', name: 'api_referentiel_types_antecedent_create', methods: ['POST'])]
    #[IsGranted(ReferentielPermissions::TYPE_ANTECEDENT_CREATE)]
    public function create(#[MapRequestPayload] CreateTypeAntecedentInput $input): JsonResponse
    {
        return $this->apiSuccess(
            $this->serialize($this->typeAntecedentService->create($input)),
            'Type d\'antécédent créé avec succès.',
            Response::HTTP_CREATED,
        );
    }

    #[Route('/{id}', name: 'api_referentiel_types_antecedent_update', methods: ['PUT'])]
    #[IsGranted(ReferentielPermissions::TYPE_ANTECEDENT_UPDATE)]
    public function update(int $id, #[MapRequestPayload] UpdateTypeAntecedentInput $input): JsonResponse
    {
        $typeAntecedent = $this->typeAntecedentService->getById($id);
        $this->denyAccessUnlessGranted(ReferentielPermissions::TYPE_ANTECEDENT_UPDATE, $typeAntecedent);

        return $this->apiSuccess(
            $this->serialize($this->typeAntecedentService->update($id, $input)),
            'Type d\'antécédent mis à jour avec succès.',
        );
    }

    #[Route('/{id}', name: 'api_referentiel_types_antecedent_delete', methods: ['DELETE'])]
    #[IsGranted(ReferentielPermissions::TYPE_ANTECEDENT_DELETE)]
    public function delete(int $id): JsonResponse
    {
        $typeAntecedent = $this->typeAntecedentService->getById($id);
        $this->denyAccessUnlessGranted(ReferentielPermissions::TYPE_ANTECEDENT_DELETE, $typeAntecedent);

        $this->typeAntecedentService->delete($id);

        return $this->apiSuccess(message: 'Type d\'antécédent supprimé avec succès.');
    }

    private function serialize(TypeAntecedent $typeAntecedent): array
    {
        return [
            'id' => $typeAntecedent->getId(),
            'code' => $typeAntecedent->getCode(),
            'libelle' => $typeAntecedent->getLibelle(),
            'createdAt' => $typeAntecedent->getCreatedAt()?->format(\DateTimeInterface::ATOM),
        ];
    }
}
