<?php

namespace App\Controller\Api\Referentiel;

use App\Controller\Api\Trait\ExportResponseTrait;
use App\Controller\Api\Trait\JsonResponseTrait;
use App\DTO\Referentiel\CreateTypeExamenInput;
use App\DTO\Referentiel\ReferentielListQuery;
use App\DTO\Referentiel\UpdateTypeExamenInput;
use App\Security\Permission\ReferentielPermissions;
use App\Service\Export\TableExportService;
use App\Service\Referentiel\TypeExamenService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryString;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/v1/referentiel/types-examen')]
final class TypeExamenController extends AbstractController
{
    use JsonResponseTrait;
    use ExportResponseTrait;

    public function __construct(
        private readonly TypeExamenService $typeExamenService,
    ) {
    }

    #[Route('', name: 'api_referentiel_types_examen_index', methods: ['GET'])]
    #[IsGranted(ReferentielPermissions::TYPE_EXAMEN_READ)]
    public function index(#[MapQueryString] ReferentielListQuery $query = new ReferentielListQuery()): JsonResponse
    {
        $result = $this->typeExamenService->paginate($query);

        return $this->apiPaginatedSuccess(
            $result->items,
            $result->page,
            $result->limit,
            $result->total,
            'Liste des types d\'examen récupérée avec succès.',
        );
    }

    #[Route('/export', name: 'api_referentiel_types_examen_export', methods: ['GET'])]
    #[IsGranted(ReferentielPermissions::TYPE_EXAMEN_EXPORT)]
    public function export(
        Request $request,
        TableExportService $tableExportService,
        #[MapQueryString] ReferentielListQuery $query = new ReferentielListQuery(),
    ): Response {
        return $this->createTableExportResponse(
            $request,
            $tableExportService,
            ['N°', 'Code', 'Libellé', 'Nb examens'],
            $this->typeExamenService->buildExportRows($query),
            'Liste des types d\'examen',
            'types_examen',
            'Aucun type d\'examen trouvé pour les filtres sélectionnés.',
        );
    }

    #[Route('/{id}', name: 'api_referentiel_types_examen_show', methods: ['GET'])]
    #[IsGranted(ReferentielPermissions::TYPE_EXAMEN_READ)]
    public function show(int $id): JsonResponse
    {
        $typeExamen = $this->typeExamenService->getById($id);
        $this->denyAccessUnlessGranted(ReferentielPermissions::TYPE_EXAMEN_READ, $typeExamen);

        return $this->apiSuccess(
            $this->typeExamenService->serializeSummary($typeExamen),
            'Type d\'examen récupéré avec succès.',
        );
    }

    #[Route('', name: 'api_referentiel_types_examen_create', methods: ['POST'])]
    #[IsGranted(ReferentielPermissions::TYPE_EXAMEN_CREATE)]
    public function create(#[MapRequestPayload] CreateTypeExamenInput $input): JsonResponse
    {
        return $this->apiSuccess(
            $this->typeExamenService->serializeSummary($this->typeExamenService->create($input)),
            'Type d\'examen créé avec succès.',
            Response::HTTP_CREATED,
        );
    }

    #[Route('/{id}', name: 'api_referentiel_types_examen_update', methods: ['PUT'])]
    #[IsGranted(ReferentielPermissions::TYPE_EXAMEN_UPDATE)]
    public function update(int $id, #[MapRequestPayload] UpdateTypeExamenInput $input): JsonResponse
    {
        $typeExamen = $this->typeExamenService->getById($id);
        $this->denyAccessUnlessGranted(ReferentielPermissions::TYPE_EXAMEN_UPDATE, $typeExamen);

        return $this->apiSuccess(
            $this->typeExamenService->serializeSummary($this->typeExamenService->update($id, $input)),
            'Type d\'examen mis à jour avec succès.',
        );
    }

    #[Route('/{id}', name: 'api_referentiel_types_examen_delete', methods: ['DELETE'])]
    #[IsGranted(ReferentielPermissions::TYPE_EXAMEN_DELETE)]
    public function delete(int $id): JsonResponse
    {
        $typeExamen = $this->typeExamenService->getById($id);
        $this->denyAccessUnlessGranted(ReferentielPermissions::TYPE_EXAMEN_DELETE, $typeExamen);

        $this->typeExamenService->delete($id);

        return $this->apiSuccess(message: 'Type d\'examen supprimé avec succès.');
    }
}
