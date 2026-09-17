<?php

namespace App\Controller\Api\Rh;

use App\Controller\Api\Trait\ExportResponseTrait;
use App\Controller\Api\Trait\JsonResponseTrait;
use App\DTO\Rh\UpsertBaremePrimeInput;
use App\Security\Permission\RhPermissions;
use App\Service\Export\TableExportService;
use App\Service\Rh\BaremePrimeService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/v1/rh/paie')]
#[IsGranted('ROLE_PERSONNEL')]
final class BaremePrimeController extends AbstractController
{
    use JsonResponseTrait;
    use ExportResponseTrait;

    public function __construct(
        private readonly BaremePrimeService $baremePrimeService,
    ) {
    }

    #[Route('/lookups', name: 'api_rh_paie_lookups', methods: ['GET'])]
    #[IsGranted(RhPermissions::PAIE_READ)]
    public function lookups(): JsonResponse
    {
        return $this->apiSuccess(
            $this->baremePrimeService->lookups(),
            'Listes du barème récupérées avec succès.',
        );
    }

    #[Route('/baremes', name: 'api_rh_paie_baremes_index', methods: ['GET'])]
    #[IsGranted(RhPermissions::PAIE_READ)]
    public function index(): JsonResponse
    {
        return $this->apiSuccess(
            $this->baremePrimeService->list(),
            'Barème de prime locale récupéré avec succès.',
        );
    }

    #[Route('/baremes', name: 'api_rh_paie_baremes_upsert', methods: ['POST'])]
    #[IsGranted(RhPermissions::PAIE_UPDATE)]
    public function upsert(#[MapRequestPayload] UpsertBaremePrimeInput $input): JsonResponse
    {
        $bareme = $this->baremePrimeService->upsert(
            $input->gradeId,
            $input->fonctionId,
            $input->montant,
        );

        return $this->apiSuccess(
            $this->baremePrimeService->serialize($bareme),
            'Ligne de barème enregistrée.',
            Response::HTTP_CREATED,
        );
    }

    #[Route('/baremes/export', name: 'api_rh_paie_baremes_export', methods: ['GET'])]
    #[IsGranted(RhPermissions::PAIE_READ)]
    public function export(Request $request, TableExportService $tableExportService): Response
    {
        return $this->createTableExportResponse(
            $request,
            $tableExportService,
            $this->baremePrimeService->exportHeaders(),
            $this->baremePrimeService->exportRows(),
            $this->baremePrimeService->exportTitle(),
            $this->baremePrimeService->exportFilenamePrefix(),
            'Aucun tarif.',
        );
    }

    #[Route('/baremes/{id}', name: 'api_rh_paie_baremes_update', methods: ['PUT'], requirements: ['id' => '\d+'])]
    #[IsGranted(RhPermissions::PAIE_UPDATE)]
    public function update(int $id, #[MapRequestPayload] UpsertBaremePrimeInput $input): JsonResponse
    {
        $bareme = $this->baremePrimeService->update(
            $id,
            $input->gradeId,
            $input->fonctionId,
            $input->montant,
        );

        return $this->apiSuccess(
            $this->baremePrimeService->serialize($bareme),
            'Ligne de barème mise à jour.',
        );
    }

    #[Route('/baremes/{id}', name: 'api_rh_paie_baremes_delete', methods: ['DELETE'], requirements: ['id' => '\d+'])]
    #[IsGranted(RhPermissions::PAIE_UPDATE)]
    public function delete(int $id): JsonResponse
    {
        $this->baremePrimeService->delete($id);

        return $this->apiSuccess(null, 'Ligne de barème supprimée.');
    }
}
