<?php

namespace App\Controller\Api\Pharmacie;

use App\Controller\Api\Trait\JsonResponseTrait;
use App\Repository\ServiceRepository;
use App\Security\Permission\PharmaciePermissions;
use App\Service\Pharmacie\VenteService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/v1/pharmacie')]
#[IsGranted('ROLE_PERSONNEL')]
final class LookupsController extends AbstractController
{
    use JsonResponseTrait;

    #[Route('/visites-hospitalisees', name: 'api_pharmacie_visites_hospitalisees', methods: ['GET'])]
    public function visitesHospitalisees(Request $request, VenteService $venteService): JsonResponse
    {
        if (
            !$this->isGranted(PharmaciePermissions::VENTE_READ)
            && !$this->isGranted(PharmaciePermissions::DEMANDE_SERVICE_READ)
        ) {
            throw $this->createAccessDeniedException();
        }

        $search = $request->query->get('search');
        $serviceId = $request->query->get('serviceId');

        return $this->apiSuccess(
            $venteService->listVisitesHospitalisees(
                is_string($search) ? $search : null,
                is_numeric($serviceId) ? (int) $serviceId : null,
            ),
            'Visites hospitalisées récupérées avec succès.',
        );
    }

    #[Route('/services-actifs', name: 'api_pharmacie_services_actifs', methods: ['GET'])]
    #[IsGranted(PharmaciePermissions::DEMANDE_SERVICE_READ)]
    public function servicesActifs(ServiceRepository $serviceRepository): JsonResponse
    {
        $items = [];
        foreach ($serviceRepository->findBy([], ['libelle' => 'ASC']) as $service) {
            $items[] = [
                'id' => $service->getId(),
                'code' => $service->getCode(),
                'libelle' => $service->getLibelle(),
            ];
        }

        return $this->apiSuccess($items, 'Services récupérés avec succès.');
    }
}
