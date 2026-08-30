<?php

namespace App\Controller\Api;

use App\Entity\Personnel;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/v1')]
#[IsGranted('ROLE_PERSONNEL')]
final class AuthController extends AbstractController
{
    #[Route('/me', name: 'api_me', methods: ['GET'])]
    public function me(#[CurrentUser] ?Personnel $personnel): JsonResponse
    {
        if (!$personnel instanceof Personnel) {
            return $this->json(['message' => 'Non authentifié.'], JsonResponse::HTTP_UNAUTHORIZED);
        }

        return $this->json([
            'data' => [
                'id' => $personnel->getId()?->toRfc4122(),
                'matricule' => $personnel->getMatricule(),
                'nom' => $personnel->getNom(),
                'postNom' => $personnel->getPostNom(),
                'prenom' => $personnel->getPrenom(),
                'telephone' => $personnel->getTelephone(),
                'type' => $personnel->getType(),
                'status' => $personnel->getStatus(),
                'roles' => $personnel->getRoles(),
                'roleAssignments' => $personnel->getRoleAssignmentSummary(),
                'service' => $personnel->getService()?->getLibelle(),
                'grade' => $personnel->getGrade()?->getLibelle(),
            ],
        ]);
    }
}
