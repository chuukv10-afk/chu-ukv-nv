<?php

namespace App\Controller\Api\Rh;

use App\Controller\Api\Trait\ExportResponseTrait;
use App\Controller\Api\Trait\JsonResponseTrait;
use App\DTO\Rh\OpenPaiePeriodeInput;
use App\DTO\Rh\UpdatePaieLigneInput;
use App\Entity\Personnel;
use App\Exception\ConflictException;
use App\Security\Permission\RhPermissions;
use App\Service\Export\TableExportService;
use App\Service\Rh\PaieService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/v1/rh/paie/periodes')]
#[IsGranted('ROLE_PERSONNEL')]
final class PaiePeriodesController extends AbstractController
{
    use JsonResponseTrait;
    use ExportResponseTrait;

    public function __construct(
        private readonly PaieService $paieService,
    ) {
    }

    #[Route('', name: 'api_rh_paie_periodes_index', methods: ['GET'])]
    #[IsGranted(RhPermissions::PAIE_READ)]
    public function index(): JsonResponse
    {
        return $this->apiSuccess(
            $this->paieService->listPeriodes(),
            'Périodes de paie récupérées avec succès.',
        );
    }

    #[Route('', name: 'api_rh_paie_periodes_open', methods: ['POST'])]
    #[IsGranted(RhPermissions::PAIE_CREATE)]
    public function open(
        #[MapRequestPayload] OpenPaiePeriodeInput $input,
        #[CurrentUser] ?Personnel $acteur,
    ): JsonResponse {
        $this->assertActeur($acteur);

        return $this->apiSuccess(
            $this->paieService->serializePeriode(
                $this->paieService->openPeriode($input->annee, $input->mois, $acteur),
                true,
            ),
            'Mois préparé à partir du personnel.',
            Response::HTTP_CREATED,
        );
    }

    #[Route('/{id}', name: 'api_rh_paie_periodes_show', methods: ['GET'], requirements: ['id' => '\d+'])]
    #[IsGranted(RhPermissions::PAIE_READ)]
    public function show(int $id): JsonResponse
    {
        return $this->apiSuccess(
            $this->paieService->serializePeriode($this->paieService->getPeriode($id), true),
            'État de paie récupéré avec succès.',
        );
    }

    #[Route('/{id}/generer', name: 'api_rh_paie_periodes_generer', methods: ['POST'], requirements: ['id' => '\d+'])]
    #[IsGranted(RhPermissions::PAIE_UPDATE)]
    public function generer(int $id): JsonResponse
    {
        $periode = $this->paieService->getPeriode($id);

        return $this->apiSuccess(
            $this->paieService->serializePeriode($this->paieService->generate($periode), true),
            'Liste mise à jour à partir du personnel.',
        );
    }

    #[Route('/{id}/lignes/{ligneId}', name: 'api_rh_paie_periodes_ligne', methods: ['PUT'], requirements: ['id' => '\d+', 'ligneId' => '\d+'])]
    #[IsGranted(RhPermissions::PAIE_UPDATE)]
    public function updateLigne(
        int $id,
        int $ligneId,
        #[MapRequestPayload] UpdatePaieLigneInput $input,
    ): JsonResponse {
        $periode = $this->paieService->getPeriode($id);

        $this->paieService->updateLigne($periode, $ligneId, $input);

        return $this->apiSuccess(
            $this->paieService->serializePeriode($periode, true),
            'Ligne de paie mise à jour.',
        );
    }

    #[Route('/{id}/valider', name: 'api_rh_paie_periodes_valider', methods: ['POST'], requirements: ['id' => '\d+'])]
    #[IsGranted(RhPermissions::PAIE_VALIDATE)]
    public function valider(int $id, #[CurrentUser] ?Personnel $acteur): JsonResponse
    {
        $this->assertActeur($acteur);
        $periode = $this->paieService->getPeriode($id);

        return $this->apiSuccess(
            $this->paieService->serializePeriode($this->paieService->validate($periode, $acteur), true),
            'Mois clôturé.',
        );
    }

    #[Route('/{id}/export', name: 'api_rh_paie_periodes_export', methods: ['GET'], requirements: ['id' => '\d+'])]
    #[IsGranted(RhPermissions::PAIE_READ)]
    public function export(
        Request $request,
        TableExportService $tableExportService,
        int $id,
    ): Response {
        $periode = $this->paieService->getPeriode($id);

        return $this->createTableExportResponse(
            $request,
            $tableExportService,
            $this->paieService->exportHeaders(),
            $this->paieService->exportRows($periode),
            $this->paieService->exportTitle($periode),
            $this->paieService->exportFilenamePrefix($periode),
            'Aucun agent payé.',
            pdfOrientation: 'landscape',
        );
    }

    private function assertActeur(?Personnel $acteur): void
    {
        if (!$acteur instanceof Personnel) {
            throw new ConflictException('Utilisateur non identifié.');
        }
    }
}
