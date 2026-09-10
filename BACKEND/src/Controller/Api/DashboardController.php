<?php

namespace App\Controller\Api;

use App\Controller\Api\Trait\JsonResponseTrait;
use App\Entity\Personnel;
use App\Entity\Role;
use App\Security\Permission\AdminPermissions;
use App\Service\Dashboard\DashboardService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/v1/dashboard')]
#[IsGranted('ROLE_PERSONNEL')]
final class DashboardController extends AbstractController
{
    use JsonResponseTrait;

    #[Route('', name: 'api_dashboard_index', methods: ['GET'])]
    public function index(DashboardService $dashboardService): JsonResponse
    {
        if (
            !$this->isGranted(AdminPermissions::DASHBOARD_VIEW)
            && !$this->isGranted(Personnel::buildSymfonyRoleCode(Role::CODE_ADMIN))
        ) {
            throw $this->createAccessDeniedException(
                'Vous n\'êtes pas autorisé à consulter le tableau de bord.',
            );
        }

        return $this->apiSuccess(
            $dashboardService->build(),
            'Tableau de bord récupéré avec succès.',
        );
    }
}
