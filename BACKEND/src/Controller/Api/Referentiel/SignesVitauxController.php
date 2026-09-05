<?php

namespace App\Controller\Api\Referentiel;

use App\Controller\Api\Trait\JsonResponseTrait;
use App\DTO\Referentiel\CreateSigneVitalInput;
use App\DTO\Referentiel\ReferentielListQuery;
use App\DTO\Referentiel\UpdateSigneVitalInput;
use App\Entity\SigneVital;
use App\Security\Permission\ReferentielPermissions;
use App\Service\Referentiel\SigneVitalService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryString;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/v1/referentiel/signes-vitaux')]
#[IsGranted('ROLE_PERSONNEL')]
final class SignesVitauxController extends AbstractController
{
    use JsonResponseTrait;

    #[Route('', name: 'api_referentiel_signes_vitaux_index', methods: ['GET'])]
    #[IsGranted(ReferentielPermissions::SIGNE_VITAL_READ)]
    public function index(
        SigneVitalService $signeVitalService,
        #[MapQueryString] ReferentielListQuery $query = new ReferentielListQuery(),
    ): JsonResponse {
        $result = $signeVitalService->paginate($query);

        return $this->apiPaginatedSuccess(
            $result->items,
            $result->page,
            $result->limit,
            $result->total,
            'Liste des signes vitaux récupérée avec succès.',
        );
    }

    #[Route('/triage', name: 'api_referentiel_signes_vitaux_triage', methods: ['GET'])]
    #[IsGranted(ReferentielPermissions::SIGNE_VITAL_READ)]
    public function triage(SigneVitalService $signeVitalService): JsonResponse
    {
        return $this->apiSuccess(
            $signeVitalService->listForTriage(),
            'Signes vitaux du triage récupérés avec succès.',
        );
    }

    #[Route('/meta', name: 'api_referentiel_signes_vitaux_meta', methods: ['GET'])]
    #[IsGranted(ReferentielPermissions::SIGNE_VITAL_READ)]
    public function meta(): JsonResponse
    {
        return $this->apiSuccess(
            ['statuts' => SigneVital::getStatuts()],
            'Métadonnées signes vitaux récupérées avec succès.',
        );
    }

    #[Route('/{id}', name: 'api_referentiel_signes_vitaux_show', methods: ['GET'], requirements: ['id' => '\d+'])]
    #[IsGranted(ReferentielPermissions::SIGNE_VITAL_READ)]
    public function show(SigneVitalService $signeVitalService, int $id): JsonResponse
    {
        return $this->apiSuccess(
            $signeVitalService->serializeSummary($signeVitalService->getById($id)),
            'Signe vital récupéré avec succès.',
        );
    }

    #[Route('', name: 'api_referentiel_signes_vitaux_create', methods: ['POST'])]
    #[IsGranted(ReferentielPermissions::SIGNE_VITAL_CREATE)]
    public function create(
        SigneVitalService $signeVitalService,
        #[MapRequestPayload] CreateSigneVitalInput $input,
    ): JsonResponse {
        return $this->apiSuccess(
            $signeVitalService->serializeSummary($signeVitalService->create($input)),
            'Signe vital créé avec succès.',
            Response::HTTP_CREATED,
        );
    }

    #[Route('/{id}', name: 'api_referentiel_signes_vitaux_update', methods: ['PUT'], requirements: ['id' => '\d+'])]
    #[IsGranted(ReferentielPermissions::SIGNE_VITAL_UPDATE)]
    public function update(
        SigneVitalService $signeVitalService,
        int $id,
        #[MapRequestPayload] UpdateSigneVitalInput $input,
    ): JsonResponse {
        return $this->apiSuccess(
            $signeVitalService->serializeSummary($signeVitalService->update($id, $input)),
            'Signe vital mis à jour avec succès.',
        );
    }

    #[Route('/{id}', name: 'api_referentiel_signes_vitaux_delete', methods: ['DELETE'], requirements: ['id' => '\d+'])]
    #[IsGranted(ReferentielPermissions::SIGNE_VITAL_DELETE)]
    public function delete(SigneVitalService $signeVitalService, int $id): JsonResponse
    {
        $signeVitalService->delete($id);

        return $this->apiSuccess(message: 'Signe vital supprimé avec succès.');
    }
}
