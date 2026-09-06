<?php

namespace App\Service\Pharmacie;

use App\DTO\Pharmacie\PharmacieListQuery;
use App\Entity\DemandeService;
use App\Entity\Vente;
use App\Repository\DemandeServiceRepository;
use App\Repository\VenteRepository;
use Symfony\Component\Validator\Exception\ValidationFailedException;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final class RecetteService
{
    public function __construct(
        private readonly VenteRepository $venteRepository,
        private readonly DemandeServiceRepository $demandeServiceRepository,
        private readonly ValidatorInterface $validator,
    ) {
    }

    /** @return array{items: list<array<string, mixed>>, pagination: array<string, int>, totaux: array<string, mixed>} */
    public function journal(PharmacieListQuery $query): array
    {
        $errors = $this->validator->validate($query);
        if (count($errors) > 0) {
            throw new ValidationFailedException($query, $errors);
        }

        $type = strtoupper(trim((string) $query->type));
        $rows = [];

        if ('' === $type || 'VENTE' === $type) {
            foreach ($this->venteRepository->findForRecettes($query->dateFrom, $query->dateTo, $query->search) as $vente) {
                $rows[] = $this->serializeVente($vente);
            }
        }
        if ('' === $type || 'SERVICE' === $type) {
            foreach ($this->demandeServiceRepository->findForRecettes($query->dateFrom, $query->dateTo, $query->search) as $demande) {
                $rows[] = $this->serializeDemande($demande);
            }
        }

        usort($rows, static function (array $left, array $right): int {
            return strcmp((string) $right['date'], (string) $left['date']);
        });

        $totaux = $this->buildTotaux($rows);
        $total = count($rows);
        $offset = max(0, ($query->page - 1) * $query->limit);
        $items = array_slice($rows, $offset, $query->limit);

        return [
            'items' => $items,
            'pagination' => [
                'page' => $query->page,
                'limit' => $query->limit,
                'total' => $total,
                'totalPages' => $total > 0 ? (int) ceil($total / $query->limit) : 0,
            ],
            'totaux' => $totaux,
        ];
    }

    /** @param list<array<string, mixed>> $rows */
    private function buildTotaux(array $rows): array
    {
        $encaisseVentes = 0.0;
        $encaisseServices = 0.0;

        foreach ($rows as $row) {
            if ('ENCAISSEE' !== $row['statut']) {
                continue;
            }
            $montant = (float) $row['montant'];
            if ('VENTE' === $row['type']) {
                $encaisseVentes += $montant;
            } else {
                $encaisseServices += $montant;
            }
        }

        return [
            'encaisseVentes' => $this->money($encaisseVentes),
            'encaisseServices' => $this->money($encaisseServices),
            'encaisseTotal' => $this->money($encaisseVentes + $encaisseServices),
            'creancesOuvertes' => $this->demandeServiceRepository->sumCreancesOuvertes(),
            'creancesCount' => $this->demandeServiceRepository->countCreancesOuvertes(),
        ];
    }

    /** @return array<string, mixed> */
    private function serializeVente(Vente $vente): array
    {
        $patient = $vente->getPatient();
        $date = $vente->getDateVente() ?? $vente->getCreatedAt();
        $origine = $vente->getOrigine();
        if (Vente::ORIGINE_HOSPITALISE === $origine) {
            $libelle = 'Hospitalisé';
            if (null !== $patient) {
                $libelle .= ' — ' . $patient->getFullName();
            }
        } elseif (Vente::CLIENT_PATIENT === $vente->getClientType()) {
            $libelle = 'Patient — ' . ($patient?->getFullName() ?? '—');
        } else {
            $libelle = 'Passant — ' . ($vente->getClientNom() ?: '—');
        }

        return [
            'id' => $vente->getId(),
            'type' => 'VENTE',
            'numero' => $vente->getNumero(),
            'date' => $date?->format(\DateTimeInterface::ATOM),
            'libelle' => $libelle,
            'montant' => $vente->getMontantTotal(),
            'statut' => 'ENCAISSEE',
            'origine' => $origine,
        ];
    }

    /** @return array<string, mixed> */
    private function serializeDemande(DemandeService $demande): array
    {
        $service = $demande->getService();
        $patient = $demande->getVisite()?->getDpi()?->getPatient();
        $libelle = $service ? 'Service — ' . $service->getLibelle() : 'Service';
        if (null !== $patient) {
            $libelle .= ' · ' . $patient->getFullName();
        }

        $date = $demande->getPayeAt()
            ?? $demande->getDelivreeAt()
            ?? $demande->getUpdatedAt()
            ?? $demande->getCreatedAt();

        return [
            'id' => $demande->getId(),
            'type' => 'SERVICE',
            'numero' => $demande->getNumero(),
            'date' => $date?->format(\DateTimeInterface::ATOM),
            'libelle' => $libelle,
            'montant' => $demande->getMontantTotal(),
            'statut' => DemandeService::PAIEMENT_PAYEE === $demande->getStatutPaiement() ? 'ENCAISSEE' : 'IMPAYEE',
            'origine' => 'SERVICE',
        ];
    }

    private function money(float $value): string
    {
        return number_format($value, 4, '.', '');
    }
}
