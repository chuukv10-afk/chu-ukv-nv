<?php

namespace App\Controller\Api;

use App\Controller\Api\Trait\JsonResponseTrait;
use App\DTO\Me\ChangeMePasswordInput;
use App\DTO\Me\UpdateMeProfileInput;
use App\Entity\Personnel;
use App\Security\Permission\AdminPermissions;
use App\Service\Personnel\MeProfileService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/v1/me')]
#[IsGranted('ROLE_PERSONNEL')]
final class MeProfileController extends AbstractController
{
    use JsonResponseTrait;

    public function __construct(
        private readonly MeProfileService $meProfileService,
    ) {
    }

    #[Route('', name: 'api_me_profile_update', methods: ['PUT'])]
    #[IsGranted(AdminPermissions::PROFIL_IDENTITE_UPDATE)]
    public function update(
        #[MapRequestPayload] UpdateMeProfileInput $input,
        #[CurrentUser] ?Personnel $personnel,
    ): JsonResponse {
        $personnel = $this->requirePersonnel($personnel);

        return $this->apiSuccess(
            $this->meProfileService->serialize($this->meProfileService->updateProfile($personnel, $input)),
            'Profil mis à jour avec succès.',
        );
    }

    #[Route('/password', name: 'api_me_password_update', methods: ['PUT'])]
    #[IsGranted(AdminPermissions::PROFIL_AUTH_UPDATE)]
    public function changePassword(
        #[MapRequestPayload] ChangeMePasswordInput $input,
        #[CurrentUser] ?Personnel $personnel,
    ): JsonResponse {
        $personnel = $this->requirePersonnel($personnel);
        $this->meProfileService->changePassword($personnel, $input);

        return $this->apiSuccess(
            $this->meProfileService->serialize($personnel),
            'Mot de passe mis à jour avec succès.',
        );
    }

    private function requirePersonnel(?Personnel $personnel): Personnel
    {
        if (!$personnel instanceof Personnel) {
            throw $this->createAccessDeniedException('Non authentifié.');
        }

        return $personnel;
    }
}
