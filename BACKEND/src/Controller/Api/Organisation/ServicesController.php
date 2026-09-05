<?php

namespace App\Controller\Api\Organisation;

use App\Controller\Api\Trait\ExportResponseTrait;
use App\Controller\Api\Trait\JsonResponseTrait;
use App\DTO\Organisation\CreateServiceInput;
use App\DTO\Organisation\ServiceListQuery;
use App\DTO\Organisation\UpdateServiceInput;
use App\Entity\Service;
use App\Security\Permission\OrganisationPermissions;
use App\Service\Export\TableExportService;
use App\Service\Organisation\ServiceService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryString;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/v1/organisation/services')]
final class ServicesController extends AbstractController
{
    use JsonResponseTrait;
    use ExportResponseTrait;

    public function __construct(
        private readonly ServiceService $serviceService,
    ) {
    }

    #[Route('', name: 'api_organisation_services_index', methods: ['GET'])]
    #[IsGranted(OrganisationPermissions::SERVICE_READ)]
    public function index(#[MapQueryString] ServiceListQuery $query = new ServiceListQuery()): JsonResponse
    {
        $result = $this->serviceService->paginate($query);

        return $this->apiPaginatedSuccess(
            $result->items,
            $result->page,
            $result->limit,
            $result->total,
            'Liste des services récupérée avec succès.',
        );
    }

    #[Route('/export', name: 'api_organisation_services_export', methods: ['GET'])]
    #[IsGranted(OrganisationPermissions::SERVICE_EXPORT)]
    public function export(
        Request $request,
        TableExportService $tableExportService,
        #[MapQueryString] ServiceListQuery $query = new ServiceListQuery(),
    ): Response {
        return $this->createTableExportResponse(
            $request,
            $tableExportService,
            ['N°', 'Code', 'Libellé', 'Département', 'Nb personnel'],
            $this->serviceService->buildExportRows($query),
            'Liste des services',
            'services',
            'Aucun service trouvé pour les filtres sélectionnés.',
        );
    }

    #[Route('/{id}', name: 'api_organisation_services_show', methods: ['GET'])]
    #[IsGranted(OrganisationPermissions::SERVICE_READ)]
    public function show(int $id): JsonResponse
    {
        $service = $this->serviceService->getById($id);
        $this->denyAccessUnlessGranted(OrganisationPermissions::SERVICE_READ, $service);

        return $this->apiSuccess(
            $this->serialize($service),
            'Service récupéré avec succès.',
        );
    }

    #[Route('', name: 'api_organisation_services_create', methods: ['POST'])]
    #[IsGranted(OrganisationPermissions::SERVICE_CREATE)]
    public function create(#[MapRequestPayload] CreateServiceInput $input): JsonResponse
    {
        return $this->apiSuccess(
            $this->serialize($this->serviceService->create($input)),
            'Service créé avec succès.',
            Response::HTTP_CREATED,
        );
    }

    #[Route('/{id}', name: 'api_organisation_services_update', methods: ['PUT'])]
    #[IsGranted(OrganisationPermissions::SERVICE_UPDATE)]
    public function update(int $id, #[MapRequestPayload] UpdateServiceInput $input): JsonResponse
    {
        $service = $this->serviceService->getById($id);
        $this->denyAccessUnlessGranted(OrganisationPermissions::SERVICE_UPDATE, $service);

        return $this->apiSuccess(
            $this->serialize($this->serviceService->update($id, $input)),
            'Service mis à jour avec succès.',
        );
    }

    #[Route('/{id}', name: 'api_organisation_services_delete', methods: ['DELETE'])]
    #[IsGranted(OrganisationPermissions::SERVICE_DELETE)]
    public function delete(int $id): JsonResponse
    {
        $service = $this->serviceService->getById($id);
        $this->denyAccessUnlessGranted(OrganisationPermissions::SERVICE_DELETE, $service);

        $this->serviceService->delete($id);

        return $this->apiSuccess(message: 'Service supprimé avec succès.');
    }

    private function serialize(Service $service): array
    {
        return $this->serviceService->serializeSummary($service);
    }
}
