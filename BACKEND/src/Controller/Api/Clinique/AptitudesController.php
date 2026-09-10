<?php

namespace App\Controller\Api\Clinique;

use App\Controller\Api\Trait\ExportResponseTrait;
use App\Controller\Api\Trait\JsonResponseTrait;
use App\DTO\Clinique\AptitudeListQuery;
use App\DTO\Clinique\UpsertAptitudeInput;
use App\Security\Permission\CliniquePermissions;
use App\Service\Clinique\AptitudePdfService;
use App\Service\Clinique\AptitudeService;
use App\Service\Export\TableExportService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryString;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/v1/clinique/aptitudes')]
#[IsGranted('ROLE_PERSONNEL')]
final class AptitudesController extends AbstractController
{
    use ExportResponseTrait;
    use JsonResponseTrait;

    public function __construct(
        private readonly AptitudeService $aptitudeService,
        private readonly AptitudePdfService $aptitudePdfService,
    ) {
    }

    #[Route('', name: 'api_clinique_aptitudes_index', methods: ['GET'])]
    #[IsGranted(CliniquePermissions::APTITUDE_READ)]
    public function index(#[MapQueryString] AptitudeListQuery $query = new AptitudeListQuery()): JsonResponse
    {
        $result = $this->aptitudeService->paginate($query);

        return $this->apiPaginatedSuccess(
            $result->items,
            $result->page,
            $result->limit,
            $result->total,
            'Liste des certificats d\'aptitude récupérée avec succès.',
        );
    }

    #[Route('/export', name: 'api_clinique_aptitudes_export', methods: ['GET'])]
    #[IsGranted(CliniquePermissions::APTITUDE_EXPORT)]
    public function export(
        Request $request,
        TableExportService $tableExportService,
        #[MapQueryString] AptitudeListQuery $query = new AptitudeListQuery(),
    ): Response {
        return $this->createTableExportResponse(
            $request,
            $tableExportService,
            $this->aptitudeService->exportHeaders(),
            $this->aptitudeService->buildExportRows($query),
            'Certificats d\'aptitude physique',
            'certificats-aptitude',
            'Aucun certificat trouvé pour les filtres sélectionnés.',
        );
    }

    #[Route('/lookups/services', name: 'api_clinique_aptitudes_services', methods: ['GET'])]
    #[IsGranted(CliniquePermissions::APTITUDE_READ)]
    public function services(): JsonResponse
    {
        return $this->apiSuccess($this->aptitudeService->listServices(), 'Services récupérés avec succès.');
    }

    #[Route('/meta', name: 'api_clinique_aptitudes_meta', methods: ['GET'])]
    #[IsGranted(CliniquePermissions::APTITUDE_READ)]
    public function meta(): JsonResponse
    {
        return $this->apiSuccess($this->aptitudeService->meta(), 'Métadonnées aptitude récupérées avec succès.');
    }

    #[Route('', name: 'api_clinique_aptitudes_create', methods: ['POST'])]
    #[IsGranted(CliniquePermissions::APTITUDE_CREATE)]
    public function create(#[MapRequestPayload] UpsertAptitudeInput $input): JsonResponse
    {
        return $this->apiSuccess(
            $this->aptitudeService->serializeDetail($this->aptitudeService->create($input)),
            'Certificat d\'aptitude créé avec succès.',
            Response::HTTP_CREATED,
        );
    }

    #[Route('/{id}/pdf', name: 'api_clinique_aptitudes_pdf', methods: ['GET'], requirements: ['id' => '\d+'])]
    #[IsGranted(CliniquePermissions::APTITUDE_EXPORT)]
    public function pdf(int $id): Response
    {
        return $this->aptitudePdfService->createResponse($this->aptitudeService->getById($id));
    }

    #[Route('/{id}/signer', name: 'api_clinique_aptitudes_signer', methods: ['POST'], requirements: ['id' => '\d+'])]
    #[IsGranted(CliniquePermissions::APTITUDE_SIGN)]
    public function signer(int $id): JsonResponse
    {
        return $this->apiSuccess(
            $this->aptitudeService->serializeDetail($this->aptitudeService->signer($id)),
            'Certificat d\'aptitude signé avec succès.',
        );
    }

    #[Route('/{id}/annuler', name: 'api_clinique_aptitudes_annuler', methods: ['POST'], requirements: ['id' => '\d+'])]
    #[IsGranted(CliniquePermissions::APTITUDE_SIGN)]
    public function annuler(int $id): JsonResponse
    {
        return $this->apiSuccess(
            $this->aptitudeService->serializeDetail($this->aptitudeService->annuler($id)),
            'Certificat d\'aptitude annulé.',
        );
    }

    #[Route('/{id}', name: 'api_clinique_aptitudes_show', methods: ['GET'], requirements: ['id' => '\d+'])]
    #[IsGranted(CliniquePermissions::APTITUDE_READ)]
    public function show(int $id): JsonResponse
    {
        return $this->apiSuccess(
            $this->aptitudeService->serializeDetail($this->aptitudeService->getById($id)),
            'Certificat d\'aptitude récupéré avec succès.',
        );
    }

    #[Route('/{id}', name: 'api_clinique_aptitudes_update', methods: ['PUT'], requirements: ['id' => '\d+'])]
    #[IsGranted(CliniquePermissions::APTITUDE_UPDATE)]
    public function update(int $id, #[MapRequestPayload] UpsertAptitudeInput $input): JsonResponse
    {
        return $this->apiSuccess(
            $this->aptitudeService->serializeDetail($this->aptitudeService->update($id, $input)),
            'Certificat d\'aptitude mis à jour avec succès.',
        );
    }

    #[Route('/{id}', name: 'api_clinique_aptitudes_delete', methods: ['DELETE'], requirements: ['id' => '\d+'])]
    #[IsGranted(CliniquePermissions::APTITUDE_DELETE)]
    public function delete(int $id): JsonResponse
    {
        $this->aptitudeService->delete($id);

        return $this->apiSuccess(message: 'Certificat d\'aptitude supprimé avec succès.');
    }
}
