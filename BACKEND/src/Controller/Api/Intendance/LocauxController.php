<?php

namespace App\Controller\Api\Intendance;

use App\Controller\Api\Trait\JsonResponseTrait;
use App\DTO\Intendance\CreateLocalInput;
use App\DTO\Intendance\LocalListQuery;
use App\DTO\Intendance\UpdateLocalInput;
use App\Entity\LocalIntendance;
use App\Security\Permission\IntendancePermissions;
use App\Service\Intendance\LocalIntendanceService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryString;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/v1/intendance/locaux')]
#[IsGranted('ROLE_PERSONNEL')]
final class LocauxController extends AbstractController
{
    use JsonResponseTrait;

    #[Route('', name: 'api_intendance_locaux_index', methods: ['GET'])]
    #[IsGranted(IntendancePermissions::LOCAL_READ)]
    public function index(
        LocalIntendanceService $localIntendanceService,
        #[MapQueryString] LocalListQuery $query = new LocalListQuery(),
    ): JsonResponse {
        $result = $localIntendanceService->paginate($query);

        return $this->apiPaginatedSuccess($result->items, $result->page, $result->limit, $result->total, 'Liste des locaux récupérée avec succès.');
    }

    #[Route('/actifs', name: 'api_intendance_locaux_actifs', methods: ['GET'])]
    public function actifs(LocalIntendanceService $localIntendanceService, Request $request): JsonResponse
    {
        if (
            !$this->isGranted(IntendancePermissions::LOCAL_READ)
            && !$this->isGranted(IntendancePermissions::BIEN_READ)
            && !$this->isGranted(IntendancePermissions::BIEN_CREATE)
            && !$this->isGranted(IntendancePermissions::BIEN_UPDATE)
        ) {
            throw $this->createAccessDeniedException();
        }

        $serviceId = (int) $request->query->get('serviceId', 0);
        if ($serviceId <= 0) {
            return $this->apiSuccess([], 'Locaux actifs récupérés avec succès.');
        }

        return $this->apiSuccess($localIntendanceService->listActifs($serviceId), 'Locaux actifs récupérés avec succès.');
    }

    #[Route('/meta', name: 'api_intendance_locaux_meta', methods: ['GET'])]
    #[IsGranted(IntendancePermissions::LOCAL_READ)]
    public function meta(): JsonResponse
    {
        return $this->apiSuccess(['statuts' => LocalIntendance::getStatuts()], 'Métadonnées locaux récupérées avec succès.');
    }

    #[Route('/{id}', name: 'api_intendance_locaux_show', methods: ['GET'], requirements: ['id' => '\d+'])]
    #[IsGranted(IntendancePermissions::LOCAL_READ)]
    public function show(LocalIntendanceService $localIntendanceService, int $id): JsonResponse
    {
        return $this->apiSuccess($localIntendanceService->serializeSummary($localIntendanceService->getById($id)), 'Local récupéré avec succès.');
    }

    #[Route('', name: 'api_intendance_locaux_create', methods: ['POST'])]
    #[IsGranted(IntendancePermissions::LOCAL_CREATE)]
    public function create(
        LocalIntendanceService $localIntendanceService,
        #[MapRequestPayload] CreateLocalInput $input,
    ): JsonResponse {
        return $this->apiSuccess(
            $localIntendanceService->serializeSummary($localIntendanceService->create($input)),
            'Local créé avec succès.',
            Response::HTTP_CREATED,
        );
    }

    #[Route('/{id}', name: 'api_intendance_locaux_update', methods: ['PUT'], requirements: ['id' => '\d+'])]
    #[IsGranted(IntendancePermissions::LOCAL_UPDATE)]
    public function update(
        LocalIntendanceService $localIntendanceService,
        int $id,
        #[MapRequestPayload] UpdateLocalInput $input,
    ): JsonResponse {
        return $this->apiSuccess(
            $localIntendanceService->serializeSummary($localIntendanceService->update($id, $input)),
            'Local mis à jour avec succès.',
        );
    }

    #[Route('/{id}', name: 'api_intendance_locaux_delete', methods: ['DELETE'], requirements: ['id' => '\d+'])]
    #[IsGranted(IntendancePermissions::LOCAL_DELETE)]
    public function delete(LocalIntendanceService $localIntendanceService, int $id): JsonResponse
    {
        $localIntendanceService->delete($id);

        return $this->apiSuccess(message: 'Local supprimé avec succès.');
    }
}
