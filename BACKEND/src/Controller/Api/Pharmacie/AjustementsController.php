<?php

namespace App\Controller\Api\Pharmacie;

use App\Controller\Api\Trait\JsonResponseTrait;
use App\DTO\Pharmacie\CreateAjustementInput;
use App\Security\Permission\PharmaciePermissions;
use App\Service\Pharmacie\AjustementService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/v1/pharmacie/ajustements')]
#[IsGranted('ROLE_PERSONNEL')]
final class AjustementsController extends AbstractController
{
    use JsonResponseTrait;

    #[Route('', name: 'api_pharmacie_ajustements_create', methods: ['POST'])]
    #[IsGranted(PharmaciePermissions::AJUSTEMENT_CREATE)]
    public function create(AjustementService $ajustementService, #[MapRequestPayload] CreateAjustementInput $input): JsonResponse
    {
        return $this->apiSuccess($ajustementService->create($input), 'Ajustement enregistré, stock mis à jour.', Response::HTTP_CREATED);
    }
}
