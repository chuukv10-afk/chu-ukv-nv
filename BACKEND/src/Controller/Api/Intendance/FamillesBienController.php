<?php

namespace App\Controller\Api\Intendance;

use App\Controller\Api\Trait\JsonResponseTrait;
use App\DTO\Intendance\CreateFamilleBienInput;
use App\DTO\Intendance\UpdateFamilleBienInput;
use App\DTO\Referentiel\ReferentielListQuery;
use App\Entity\FamilleBien;
use App\Security\Permission\IntendancePermissions;
use App\Service\Intendance\FamilleBienService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryString;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/v1/intendance/familles')]
#[IsGranted('ROLE_PERSONNEL')]
final class FamillesBienController extends AbstractController
{
    use JsonResponseTrait;

    #[Route('', name: 'api_intendance_familles_index', methods: ['GET'])]
    #[IsGranted(IntendancePermissions::FAMILLE_READ)]
    public function index(
        FamilleBienService $familleBienService,
        #[MapQueryString] ReferentielListQuery $query = new ReferentielListQuery(),
    ): JsonResponse {
        $result = $familleBienService->paginate($query);

        return $this->apiPaginatedSuccess($result->items, $result->page, $result->limit, $result->total, 'Liste des familles récupérée avec succès.');
    }

    #[Route('/actifs', name: 'api_intendance_familles_actifs', methods: ['GET'])]
    public function actifs(FamilleBienService $familleBienService): JsonResponse
    {
        if (
            !$this->isGranted(IntendancePermissions::FAMILLE_READ)
            && !$this->isGranted(IntendancePermissions::TYPE_READ)
            && !$this->isGranted(IntendancePermissions::BIEN_READ)
            && !$this->isGranted(IntendancePermissions::BIEN_CREATE)
        ) {
            throw $this->createAccessDeniedException();
        }

        return $this->apiSuccess($familleBienService->listActifs(), 'Familles actives récupérées avec succès.');
    }

    #[Route('/meta', name: 'api_intendance_familles_meta', methods: ['GET'])]
    #[IsGranted(IntendancePermissions::FAMILLE_READ)]
    public function meta(): JsonResponse
    {
        return $this->apiSuccess(['statuts' => FamilleBien::getStatuts()], 'Métadonnées familles récupérées avec succès.');
    }

    #[Route('/{id}', name: 'api_intendance_familles_show', methods: ['GET'], requirements: ['id' => '\d+'])]
    #[IsGranted(IntendancePermissions::FAMILLE_READ)]
    public function show(FamilleBienService $familleBienService, int $id): JsonResponse
    {
        return $this->apiSuccess($familleBienService->serializeSummary($familleBienService->getById($id)), 'Famille récupérée avec succès.');
    }

    #[Route('', name: 'api_intendance_familles_create', methods: ['POST'])]
    #[IsGranted(IntendancePermissions::FAMILLE_CREATE)]
    public function create(
        FamilleBienService $familleBienService,
        #[MapRequestPayload] CreateFamilleBienInput $input,
    ): JsonResponse {
        return $this->apiSuccess(
            $familleBienService->serializeSummary($familleBienService->create($input)),
            'Famille créée avec succès.',
            Response::HTTP_CREATED,
        );
    }

    #[Route('/{id}', name: 'api_intendance_familles_update', methods: ['PUT'], requirements: ['id' => '\d+'])]
    #[IsGranted(IntendancePermissions::FAMILLE_UPDATE)]
    public function update(
        FamilleBienService $familleBienService,
        int $id,
        #[MapRequestPayload] UpdateFamilleBienInput $input,
    ): JsonResponse {
        return $this->apiSuccess(
            $familleBienService->serializeSummary($familleBienService->update($id, $input)),
            'Famille mise à jour avec succès.',
        );
    }

    #[Route('/{id}', name: 'api_intendance_familles_delete', methods: ['DELETE'], requirements: ['id' => '\d+'])]
    #[IsGranted(IntendancePermissions::FAMILLE_DELETE)]
    public function delete(FamilleBienService $familleBienService, int $id): JsonResponse
    {
        $familleBienService->delete($id);

        return $this->apiSuccess(message: 'Famille supprimée avec succès.');
    }
}
