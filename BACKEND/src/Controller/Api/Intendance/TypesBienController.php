<?php

namespace App\Controller\Api\Intendance;

use App\Controller\Api\Trait\JsonResponseTrait;
use App\DTO\Intendance\CreateTypeBienInput;
use App\DTO\Intendance\TypeBienListQuery;
use App\DTO\Intendance\UpdateTypeBienInput;
use App\Entity\TypeBien;
use App\Security\Permission\IntendancePermissions;
use App\Service\Intendance\TypeBienService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryString;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/v1/intendance/types')]
#[IsGranted('ROLE_PERSONNEL')]
final class TypesBienController extends AbstractController
{
    use JsonResponseTrait;

    #[Route('', name: 'api_intendance_types_index', methods: ['GET'])]
    #[IsGranted(IntendancePermissions::TYPE_READ)]
    public function index(
        TypeBienService $typeBienService,
        #[MapQueryString] TypeBienListQuery $query = new TypeBienListQuery(),
    ): JsonResponse {
        $result = $typeBienService->paginate($query);

        return $this->apiPaginatedSuccess($result->items, $result->page, $result->limit, $result->total, 'Liste des types récupérée avec succès.');
    }

    #[Route('/actifs', name: 'api_intendance_types_actifs', methods: ['GET'])]
    public function actifs(TypeBienService $typeBienService, Request $request): JsonResponse
    {
        if (
            !$this->isGranted(IntendancePermissions::TYPE_READ)
            && !$this->isGranted(IntendancePermissions::BIEN_READ)
            && !$this->isGranted(IntendancePermissions::BIEN_CREATE)
        ) {
            throw $this->createAccessDeniedException();
        }

        $familleId = $request->query->get('familleId');

        return $this->apiSuccess(
            $typeBienService->listActifs(is_numeric($familleId) ? (int) $familleId : null),
            'Types actifs récupérés avec succès.',
        );
    }

    #[Route('/meta', name: 'api_intendance_types_meta', methods: ['GET'])]
    #[IsGranted(IntendancePermissions::TYPE_READ)]
    public function meta(): JsonResponse
    {
        return $this->apiSuccess(['statuts' => TypeBien::getStatuts()], 'Métadonnées types récupérées avec succès.');
    }

    #[Route('/{id}', name: 'api_intendance_types_show', methods: ['GET'], requirements: ['id' => '\d+'])]
    #[IsGranted(IntendancePermissions::TYPE_READ)]
    public function show(TypeBienService $typeBienService, int $id): JsonResponse
    {
        return $this->apiSuccess($typeBienService->serializeSummary($typeBienService->getById($id)), 'Type récupéré avec succès.');
    }

    #[Route('', name: 'api_intendance_types_create', methods: ['POST'])]
    #[IsGranted(IntendancePermissions::TYPE_CREATE)]
    public function create(
        TypeBienService $typeBienService,
        #[MapRequestPayload] CreateTypeBienInput $input,
    ): JsonResponse {
        return $this->apiSuccess(
            $typeBienService->serializeSummary($typeBienService->create($input)),
            'Type créé avec succès.',
            Response::HTTP_CREATED,
        );
    }

    #[Route('/{id}', name: 'api_intendance_types_update', methods: ['PUT'], requirements: ['id' => '\d+'])]
    #[IsGranted(IntendancePermissions::TYPE_UPDATE)]
    public function update(
        TypeBienService $typeBienService,
        int $id,
        #[MapRequestPayload] UpdateTypeBienInput $input,
    ): JsonResponse {
        return $this->apiSuccess(
            $typeBienService->serializeSummary($typeBienService->update($id, $input)),
            'Type mis à jour avec succès.',
        );
    }

    #[Route('/{id}', name: 'api_intendance_types_delete', methods: ['DELETE'], requirements: ['id' => '\d+'])]
    #[IsGranted(IntendancePermissions::TYPE_DELETE)]
    public function delete(TypeBienService $typeBienService, int $id): JsonResponse
    {
        $typeBienService->delete($id);

        return $this->apiSuccess(message: 'Type supprimé avec succès.');
    }
}
