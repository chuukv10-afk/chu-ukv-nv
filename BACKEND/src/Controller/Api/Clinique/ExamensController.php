<?php

namespace App\Controller\Api\Clinique;

use App\Controller\Api\Trait\JsonResponseTrait;
use App\DTO\Clinique\CreateExamenInput;
use App\DTO\Clinique\UpdateExamenInput;
use App\Entity\Examen;
use App\Security\Permission\CliniquePermissions;
use App\Service\Clinique\ExamenService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/v1/clinique/examens')]
final class ExamensController extends AbstractController
{
    use JsonResponseTrait;

    public function __construct(
        private readonly ExamenService $examenService,
    ) {
    }

    #[Route('', name: 'api_clinique_examens_index', methods: ['GET'])]
    #[IsGranted(CliniquePermissions::EXAMEN_READ)]
    public function index(): JsonResponse
    {
        return $this->apiSuccess(
            array_map([$this, 'serialize'], $this->examenService->findAll()),
            'Liste des examens récupérée avec succès.',
        );
    }

    #[Route('/{id}', name: 'api_clinique_examens_show', methods: ['GET'])]
    #[IsGranted(CliniquePermissions::EXAMEN_READ)]
    public function show(int $id): JsonResponse
    {
        $examen = $this->examenService->getById($id);
        $this->denyAccessUnlessGranted(CliniquePermissions::EXAMEN_READ, $examen);

        return $this->apiSuccess(
            $this->serialize($examen),
            'Examen récupéré avec succès.',
        );
    }

    #[Route('', name: 'api_clinique_examens_create', methods: ['POST'])]
    #[IsGranted(CliniquePermissions::EXAMEN_CREATE)]
    public function create(#[MapRequestPayload] CreateExamenInput $input): JsonResponse
    {
        return $this->apiSuccess(
            $this->serialize($this->examenService->create($input)),
            'Examen créé avec succès.',
            Response::HTTP_CREATED,
        );
    }

    #[Route('/{id}', name: 'api_clinique_examens_update', methods: ['PUT'])]
    #[IsGranted(CliniquePermissions::EXAMEN_UPDATE)]
    public function update(int $id, #[MapRequestPayload] UpdateExamenInput $input): JsonResponse
    {
        $examen = $this->examenService->getById($id);
        $this->denyAccessUnlessGranted(CliniquePermissions::EXAMEN_UPDATE, $examen);

        return $this->apiSuccess(
            $this->serialize($this->examenService->update($id, $input)),
            'Examen mis à jour avec succès.',
        );
    }

    #[Route('/{id}', name: 'api_clinique_examens_delete', methods: ['DELETE'])]
    #[IsGranted(CliniquePermissions::EXAMEN_DELETE)]
    public function delete(int $id): JsonResponse
    {
        $examen = $this->examenService->getById($id);
        $this->denyAccessUnlessGranted(CliniquePermissions::EXAMEN_DELETE, $examen);

        $this->examenService->delete($id);

        return $this->apiSuccess(message: 'Examen supprimé avec succès.');
    }

    private function serialize(Examen $examen): array
    {
        return [
            'id' => $examen->getId(),
            'code' => $examen->getCode(),
            'libelle' => $examen->getLibelle(),
            'createdAt' => $examen->getCreatedAt()?->format(\DateTimeInterface::ATOM),
        ];
    }
}
