<?php

namespace App\Controller\Api\Referentiel;

use App\Controller\Api\Trait\JsonResponseTrait;
use App\DTO\Referentiel\CreateOrganisationPartenaireInput;
use App\DTO\Referentiel\ReferentielListQuery;
use App\DTO\Referentiel\UpdateOrganisationPartenaireInput;
use App\Security\Permission\ReferentielPermissions;
use App\Service\Referentiel\OrganisationPartenaireService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryString;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/v1/referentiel/organisations-partenaires')]
final class OrganisationsPartenairesController extends AbstractController
{
    use JsonResponseTrait;

    public function __construct(
        private readonly OrganisationPartenaireService $organisationPartenaireService,
    ) {
    }

    #[Route('', name: 'api_referentiel_organisations_partenaires_index', methods: ['GET'])]
    #[IsGranted(ReferentielPermissions::ORGANISATION_PARTENAIRE_READ)]
    public function index(#[MapQueryString] ReferentielListQuery $query = new ReferentielListQuery()): JsonResponse
    {
        $result = $this->organisationPartenaireService->paginate($query);

        return $this->apiPaginatedSuccess(
            $result->items,
            $result->page,
            $result->limit,
            $result->total,
            'Liste des organisations partenaires récupérée avec succès.',
        );
    }

    #[Route('/lookups', name: 'api_referentiel_organisations_partenaires_lookups', methods: ['GET'])]
    #[IsGranted(ReferentielPermissions::ORGANISATION_PARTENAIRE_READ)]
    public function lookups(): JsonResponse
    {
        return $this->apiSuccess(
            $this->organisationPartenaireService->listLookup(),
            'Organisations partenaires récupérées avec succès.',
        );
    }

    #[Route('/meta', name: 'api_referentiel_organisations_partenaires_meta', methods: ['GET'])]
    #[IsGranted(ReferentielPermissions::ORGANISATION_PARTENAIRE_READ)]
    public function meta(): JsonResponse
    {
        return $this->apiSuccess(
            $this->organisationPartenaireService->meta(),
            'Métadonnées organisations partenaires récupérées avec succès.',
        );
    }

    #[Route('/{id}', name: 'api_referentiel_organisations_partenaires_show', methods: ['GET'], requirements: ['id' => '\d+'])]
    #[IsGranted(ReferentielPermissions::ORGANISATION_PARTENAIRE_READ)]
    public function show(int $id): JsonResponse
    {
        return $this->apiSuccess(
            $this->organisationPartenaireService->serializeSummary($this->organisationPartenaireService->getById($id)),
            'Organisation partenaire récupérée avec succès.',
        );
    }

    #[Route('', name: 'api_referentiel_organisations_partenaires_create', methods: ['POST'])]
    #[IsGranted(ReferentielPermissions::ORGANISATION_PARTENAIRE_CREATE)]
    public function create(#[MapRequestPayload] CreateOrganisationPartenaireInput $input): JsonResponse
    {
        return $this->apiSuccess(
            $this->organisationPartenaireService->serializeSummary($this->organisationPartenaireService->create($input)),
            'Organisation partenaire créée avec succès.',
            Response::HTTP_CREATED,
        );
    }

    #[Route('/{id}', name: 'api_referentiel_organisations_partenaires_update', methods: ['PUT'], requirements: ['id' => '\d+'])]
    #[IsGranted(ReferentielPermissions::ORGANISATION_PARTENAIRE_UPDATE)]
    public function update(int $id, #[MapRequestPayload] UpdateOrganisationPartenaireInput $input): JsonResponse
    {
        return $this->apiSuccess(
            $this->organisationPartenaireService->serializeSummary($this->organisationPartenaireService->update($id, $input)),
            'Organisation partenaire mise à jour avec succès.',
        );
    }

    #[Route('/{id}', name: 'api_referentiel_organisations_partenaires_delete', methods: ['DELETE'], requirements: ['id' => '\d+'])]
    #[IsGranted(ReferentielPermissions::ORGANISATION_PARTENAIRE_DELETE)]
    public function delete(int $id): JsonResponse
    {
        $this->organisationPartenaireService->delete($id);

        return $this->apiSuccess(message: 'Organisation partenaire supprimée avec succès.');
    }
}
