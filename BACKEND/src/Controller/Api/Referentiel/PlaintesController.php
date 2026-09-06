<?php

namespace App\Controller\Api\Referentiel;

use App\Controller\Api\Trait\JsonResponseTrait;
use App\DTO\Referentiel\CreatePlainteInput;
use App\DTO\Referentiel\ReferentielListQuery;
use App\DTO\Referentiel\UpdatePlainteInput;
use App\Entity\Plainte;
use App\Security\Permission\ReferentielPermissions;
use App\Service\Referentiel\PlainteService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryString;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/v1/referentiel/plaintes')]
#[IsGranted('ROLE_PERSONNEL')]
final class PlaintesController extends AbstractController
{
    use JsonResponseTrait;

    #[Route('', name: 'api_referentiel_plaintes_index', methods: ['GET'])]
    #[IsGranted(ReferentielPermissions::PLAINTE_READ)]
    public function index(
        PlainteService $plainteService,
        #[MapQueryString] ReferentielListQuery $query = new ReferentielListQuery(),
    ): JsonResponse {
        $result = $plainteService->paginate($query);

        return $this->apiPaginatedSuccess(
            $result->items,
            $result->page,
            $result->limit,
            $result->total,
            'Liste des plaintes récupérée avec succès.',
        );
    }

    #[Route('/actifs', name: 'api_referentiel_plaintes_actifs', methods: ['GET'])]
    public function actifs(PlainteService $plainteService): JsonResponse
    {
        return $this->apiSuccess(
            $plainteService->listActifs(),
            'Plaintes actives récupérées avec succès.',
        );
    }

    #[Route('/meta', name: 'api_referentiel_plaintes_meta', methods: ['GET'])]
    #[IsGranted(ReferentielPermissions::PLAINTE_READ)]
    public function meta(): JsonResponse
    {
        return $this->apiSuccess(
            ['statuts' => Plainte::getStatuts()],
            'Métadonnées plaintes récupérées avec succès.',
        );
    }

    #[Route('/{id}', name: 'api_referentiel_plaintes_show', methods: ['GET'], requirements: ['id' => '\d+'])]
    #[IsGranted(ReferentielPermissions::PLAINTE_READ)]
    public function show(PlainteService $plainteService, int $id): JsonResponse
    {
        return $this->apiSuccess(
            $plainteService->serializeSummary($plainteService->getById($id)),
            'Plainte récupérée avec succès.',
        );
    }

    #[Route('', name: 'api_referentiel_plaintes_create', methods: ['POST'])]
    #[IsGranted(ReferentielPermissions::PLAINTE_CREATE)]
    public function create(
        PlainteService $plainteService,
        #[MapRequestPayload] CreatePlainteInput $input,
    ): JsonResponse {
        return $this->apiSuccess(
            $plainteService->serializeSummary($plainteService->create($input)),
            'Plainte créée avec succès.',
            Response::HTTP_CREATED,
        );
    }

    #[Route('/{id}', name: 'api_referentiel_plaintes_update', methods: ['PUT'], requirements: ['id' => '\d+'])]
    #[IsGranted(ReferentielPermissions::PLAINTE_UPDATE)]
    public function update(
        PlainteService $plainteService,
        int $id,
        #[MapRequestPayload] UpdatePlainteInput $input,
    ): JsonResponse {
        return $this->apiSuccess(
            $plainteService->serializeSummary($plainteService->update($id, $input)),
            'Plainte mise à jour avec succès.',
        );
    }

    #[Route('/{id}', name: 'api_referentiel_plaintes_delete', methods: ['DELETE'], requirements: ['id' => '\d+'])]
    #[IsGranted(ReferentielPermissions::PLAINTE_DELETE)]
    public function delete(PlainteService $plainteService, int $id): JsonResponse
    {
        $plainteService->delete($id);

        return $this->apiSuccess(message: 'Plainte supprimée avec succès.');
    }
}
