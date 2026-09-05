<?php

namespace App\Controller\Api\Referentiel;

use App\Controller\Api\Trait\JsonResponseTrait;
use App\DTO\Referentiel\CreateGradeInput;
use App\DTO\Referentiel\ReferentielListQuery;
use App\DTO\Referentiel\UpdateGradeInput;
use App\Security\Permission\ReferentielPermissions;
use App\Service\Referentiel\GradeService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryString;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/v1/referentiel/grades')]
final class GradesController extends AbstractController
{
    use JsonResponseTrait;

    public function __construct(
        private readonly GradeService $gradeService,
    ) {
    }

    #[Route('', name: 'api_referentiel_grades_index', methods: ['GET'])]
    #[IsGranted(ReferentielPermissions::GRADE_READ)]
    public function index(#[MapQueryString] ReferentielListQuery $query = new ReferentielListQuery()): JsonResponse
    {
        $result = $this->gradeService->paginate($query);

        return $this->apiPaginatedSuccess(
            $result->items,
            $result->page,
            $result->limit,
            $result->total,
            'Liste des grades récupérée avec succès.',
        );
    }

    #[Route('/{id}', name: 'api_referentiel_grades_show', methods: ['GET'])]
    #[IsGranted(ReferentielPermissions::GRADE_READ)]
    public function show(int $id): JsonResponse
    {
        $grade = $this->gradeService->getById($id);
        $this->denyAccessUnlessGranted(ReferentielPermissions::GRADE_READ, $grade);

        return $this->apiSuccess(
            $this->gradeService->serializeSummary($grade),
            'Grade récupéré avec succès.',
        );
    }

    #[Route('', name: 'api_referentiel_grades_create', methods: ['POST'])]
    #[IsGranted(ReferentielPermissions::GRADE_CREATE)]
    public function create(#[MapRequestPayload] CreateGradeInput $input): JsonResponse
    {
        return $this->apiSuccess(
            $this->gradeService->serializeSummary($this->gradeService->create($input)),
            'Grade créé avec succès.',
            Response::HTTP_CREATED,
        );
    }

    #[Route('/{id}', name: 'api_referentiel_grades_update', methods: ['PUT'])]
    #[IsGranted(ReferentielPermissions::GRADE_UPDATE)]
    public function update(int $id, #[MapRequestPayload] UpdateGradeInput $input): JsonResponse
    {
        $grade = $this->gradeService->getById($id);
        $this->denyAccessUnlessGranted(ReferentielPermissions::GRADE_UPDATE, $grade);

        return $this->apiSuccess(
            $this->gradeService->serializeSummary($this->gradeService->update($id, $input)),
            'Grade mis à jour avec succès.',
        );
    }

    #[Route('/{id}', name: 'api_referentiel_grades_delete', methods: ['DELETE'])]
    #[IsGranted(ReferentielPermissions::GRADE_DELETE)]
    public function delete(int $id): JsonResponse
    {
        $grade = $this->gradeService->getById($id);
        $this->denyAccessUnlessGranted(ReferentielPermissions::GRADE_DELETE, $grade);

        $this->gradeService->delete($id);

        return $this->apiSuccess(message: 'Grade supprimé avec succès.');
    }
}
