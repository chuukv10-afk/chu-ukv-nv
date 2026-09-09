<?php

namespace App\Controller\Api\Intendance;

use App\Controller\Api\Trait\ExportResponseTrait;
use App\Controller\Api\Trait\JsonResponseTrait;
use App\DTO\Intendance\BienListQuery;
use App\DTO\Intendance\CreateGroupeBienInput;
use App\DTO\Intendance\EtiquettesQuery;
use App\DTO\Intendance\ProposerCodeQuery;
use App\DTO\Intendance\ReformerBienInput;
use App\DTO\Intendance\TransfererBienInput;
use App\DTO\Intendance\UpsertBienInput;
use App\Entity\BienPatrimonial;
use App\Exception\ConflictException;
use App\Security\Permission\IntendancePermissions;
use App\Service\Export\TableExportService;
use App\Service\Intendance\BienPatrimonialService;
use App\Service\Intendance\EtiquettePdfService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryString;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/v1/intendance/biens')]
#[IsGranted('ROLE_PERSONNEL')]
final class BiensController extends AbstractController
{
    use ExportResponseTrait;
    use JsonResponseTrait;

    #[Route('', name: 'api_intendance_biens_index', methods: ['GET'])]
    #[IsGranted(IntendancePermissions::BIEN_READ)]
    public function index(
        BienPatrimonialService $bienPatrimonialService,
        #[MapQueryString] BienListQuery $query = new BienListQuery(),
    ): JsonResponse {
        $result = $bienPatrimonialService->paginate($query);

        return $this->apiPaginatedSuccess($result->items, $result->page, $result->limit, $result->total, 'Parc récupéré avec succès.');
    }

    #[Route('/export', name: 'api_intendance_biens_export', methods: ['GET'])]
    #[IsGranted(IntendancePermissions::BIEN_EXPORT)]
    public function export(
        Request $request,
        TableExportService $tableExportService,
        BienPatrimonialService $bienPatrimonialService,
        #[MapQueryString] BienListQuery $query = new BienListQuery(),
    ): Response {
        return $this->createTableExportResponse(
            $request,
            $tableExportService,
            $bienPatrimonialService->exportHeaders(),
            $bienPatrimonialService->buildExportRows($query),
            'Parc patrimonial — Intendance',
            'parc-intendance',
            'Aucun bien trouvé pour les filtres sélectionnés.',
            pdfOrientation: 'landscape',
        );
    }

    #[Route('/etiquettes', name: 'api_intendance_biens_etiquettes', methods: ['GET'])]
    #[IsGranted(IntendancePermissions::ETIQUETTE_GENERATE)]
    public function etiquettes(
        EtiquettePdfService $etiquettePdfService,
        BienPatrimonialService $bienPatrimonialService,
        #[MapQueryString] EtiquettesQuery $query = new EtiquettesQuery(),
    ): Response {
        $ids = $query->parsedIds();
        if ([] === $ids) {
            throw new ConflictException('Sélectionnez au moins un bien.');
        }

        return $etiquettePdfService->createResponse($bienPatrimonialService->findActifsByIds($ids));
    }

    #[Route('/par-code/{code}', name: 'api_intendance_biens_par_code', methods: ['GET'], requirements: ['code' => '.+'])]
    #[IsGranted(IntendancePermissions::BIEN_READ)]
    public function parCode(BienPatrimonialService $bienPatrimonialService, string $code): JsonResponse
    {
        return $this->apiSuccess(
            $bienPatrimonialService->serializeSummary($bienPatrimonialService->getByCode($code)),
            'Bien identifié avec succès.',
        );
    }

    #[Route('/prochain-code', name: 'api_intendance_biens_prochain_code', methods: ['GET'])]
    #[IsGranted(IntendancePermissions::BIEN_CREATE)]
    public function prochainCode(
        BienPatrimonialService $bienPatrimonialService,
        #[MapQueryString] ProposerCodeQuery $query = new ProposerCodeQuery(),
    ): JsonResponse {
        return $this->apiSuccess($bienPatrimonialService->proposerCodes($query), 'Code proposé avec succès.');
    }

    #[Route('/effectifs', name: 'api_intendance_biens_effectifs', methods: ['GET'])]
    #[IsGranted(IntendancePermissions::SYNTHESE_READ)]
    public function effectifs(
        BienPatrimonialService $bienPatrimonialService,
        #[MapQueryString] BienListQuery $query = new BienListQuery(),
    ): JsonResponse {
        return $this->apiSuccess($bienPatrimonialService->effectifs($query), 'Effectifs calculés avec succès.');
    }

    #[Route('/lookups/services', name: 'api_intendance_lookups_services', methods: ['GET'])]
    public function services(BienPatrimonialService $bienPatrimonialService): JsonResponse
    {
        if (
            !$this->isGranted(IntendancePermissions::BIEN_READ)
            && !$this->isGranted(IntendancePermissions::LOCAL_READ)
        ) {
            throw $this->createAccessDeniedException();
        }

        return $this->apiSuccess($bienPatrimonialService->listServices(), 'Services récupérés avec succès.');
    }

    #[Route('/meta', name: 'api_intendance_biens_meta', methods: ['GET'])]
    #[IsGranted(IntendancePermissions::BIEN_READ)]
    public function meta(): JsonResponse
    {
        return $this->apiSuccess(['etats' => BienPatrimonial::getEtats()], 'Métadonnées parc récupérées avec succès.');
    }

    #[Route('/groupe', name: 'api_intendance_biens_groupe', methods: ['POST'])]
    #[IsGranted(IntendancePermissions::BIEN_CREATE)]
    public function groupe(
        BienPatrimonialService $bienPatrimonialService,
        #[MapRequestPayload] CreateGroupeBienInput $input,
    ): JsonResponse {
        $items = array_map(
            [$bienPatrimonialService, 'serializeSummary'],
            $bienPatrimonialService->createGroupe($input),
        );

        return $this->apiSuccess($items, 'Biens créés avec succès.', Response::HTTP_CREATED);
    }

    #[Route('/{id}', name: 'api_intendance_biens_show', methods: ['GET'], requirements: ['id' => '\d+'])]
    #[IsGranted(IntendancePermissions::BIEN_READ)]
    public function show(BienPatrimonialService $bienPatrimonialService, int $id): JsonResponse
    {
        return $this->apiSuccess(
            $bienPatrimonialService->serializeSummary($bienPatrimonialService->getById($id)),
            'Bien récupéré avec succès.',
        );
    }

    #[Route('/{id}/historique', name: 'api_intendance_biens_historique', methods: ['GET'], requirements: ['id' => '\d+'])]
    #[IsGranted(IntendancePermissions::BIEN_READ)]
    public function historique(BienPatrimonialService $bienPatrimonialService, int $id): JsonResponse
    {
        return $this->apiSuccess($bienPatrimonialService->historique($id), 'Historique récupéré avec succès.');
    }

    #[Route('', name: 'api_intendance_biens_create', methods: ['POST'])]
    #[IsGranted(IntendancePermissions::BIEN_CREATE)]
    public function create(
        BienPatrimonialService $bienPatrimonialService,
        #[MapRequestPayload] UpsertBienInput $input,
    ): JsonResponse {
        return $this->apiSuccess(
            $bienPatrimonialService->serializeSummary($bienPatrimonialService->create($input)),
            'Bien créé avec succès.',
            Response::HTTP_CREATED,
        );
    }

    #[Route('/{id}', name: 'api_intendance_biens_update', methods: ['PUT'], requirements: ['id' => '\d+'])]
    #[IsGranted(IntendancePermissions::BIEN_UPDATE)]
    public function update(
        BienPatrimonialService $bienPatrimonialService,
        int $id,
        #[MapRequestPayload] UpsertBienInput $input,
    ): JsonResponse {
        return $this->apiSuccess(
            $bienPatrimonialService->serializeSummary($bienPatrimonialService->update($id, $input)),
            'Bien mis à jour avec succès.',
        );
    }

    #[Route('/{id}/transferer', name: 'api_intendance_biens_transferer', methods: ['POST'], requirements: ['id' => '\d+'])]
    #[IsGranted(IntendancePermissions::BIEN_UPDATE)]
    public function transferer(
        BienPatrimonialService $bienPatrimonialService,
        int $id,
        #[MapRequestPayload] TransfererBienInput $input,
    ): JsonResponse {
        return $this->apiSuccess(
            $bienPatrimonialService->serializeSummary($bienPatrimonialService->transferer($id, $input)),
            'Bien transféré avec succès.',
        );
    }

    #[Route('/{id}/reformer', name: 'api_intendance_biens_reformer', methods: ['POST'], requirements: ['id' => '\d+'])]
    #[IsGranted(IntendancePermissions::BIEN_UPDATE)]
    public function reformer(
        BienPatrimonialService $bienPatrimonialService,
        int $id,
        #[MapRequestPayload] ReformerBienInput $input,
    ): JsonResponse {
        return $this->apiSuccess(
            $bienPatrimonialService->serializeSummary($bienPatrimonialService->reformer($id, $input)),
            'Bien réformé avec succès.',
        );
    }

    #[Route('/{id}', name: 'api_intendance_biens_delete', methods: ['DELETE'], requirements: ['id' => '\d+'])]
    #[IsGranted(IntendancePermissions::BIEN_DELETE)]
    public function delete(BienPatrimonialService $bienPatrimonialService, int $id): JsonResponse
    {
        $bienPatrimonialService->delete($id);

        return $this->apiSuccess(message: 'Bien supprimé avec succès.');
    }
}
