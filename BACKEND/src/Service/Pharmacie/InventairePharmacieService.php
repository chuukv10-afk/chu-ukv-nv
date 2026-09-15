<?php

namespace App\Service\Pharmacie;

use App\DTO\Common\PaginatedResult;
use App\DTO\Pharmacie\CompterInventaireLigneInput;
use App\DTO\Pharmacie\CompterInventaireLigneSaisieInput;
use App\DTO\Pharmacie\CompterInventaireProduitInput;
use App\DTO\Pharmacie\CreateInventairePharmacieInput;
use App\DTO\Pharmacie\PharmacieListQuery;
use App\Entity\InventairePharmacie;
use App\Entity\InventairePharmacieLigne;
use App\Entity\MouvementStock;
use App\Entity\Personnel;
use App\Exception\ConflictException;
use App\Exception\NotFoundException;
use App\Repository\InventairePharmacieRepository;
use App\Repository\LotRepository;
use App\Util\CalendarDate;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Validator\Exception\ValidationFailedException;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final class InventairePharmacieService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly InventairePharmacieRepository $inventaireRepository,
        private readonly LotRepository $lotRepository,
        private readonly StockService $stockService,
        private readonly Security $security,
        private readonly ValidatorInterface $validator,
    ) {
    }

    public function paginate(PharmacieListQuery $query): PaginatedResult
    {
        $this->assertValid($query);
        $result = $this->inventaireRepository->paginate($query->page, $query->limit, $query->search, $query->statut);

        return new PaginatedResult(
            array_map([$this, 'serializeSummary'], $result['items']),
            $query->page,
            $query->limit,
            $result['total'],
        );
    }

    public function create(CreateInventairePharmacieInput $input): InventairePharmacie
    {
        $this->assertValid($input);
        if (null !== $this->inventaireRepository->findEnCours()) {
            throw new ConflictException('Une campagne d\'inventaire est déjà en cours. Clôturez-la avant d\'en ouvrir une autre.');
        }

        $lots = $this->lotRepository->findPourInventaire();
        if ([] === $lots) {
            throw new ConflictException('Aucun lot en stock à inventorier.');
        }

        $now = $this->now();
        $inventaire = (new InventairePharmacie())
            ->setNumero($this->nextNumero($now))
            ->setLibelle(trim($input->libelle))
            ->setNotes($this->nullable($input->notes))
            ->setStatut(InventairePharmacie::STATUT_EN_COURS)
            ->setDateDebut($now->setTime(0, 0));

        foreach ($lots as $lot) {
            $medicament = $lot->getMedicament();
            if (null === $medicament) {
                continue;
            }
            $ligne = (new InventairePharmacieLigne())
                ->setLot($lot)
                ->setMedicament($medicament)
                ->setNumeroLot((string) $lot->getNumeroLot())
                ->setDatePeremption($lot->getDatePeremption() ?? $now->setTime(0, 0))
                ->setQuantiteSysteme($lot->getQuantiteRestante())
                ->setCompte(false);
            $inventaire->addLigne($ligne);
        }

        if ($inventaire->getLignes()->isEmpty()) {
            throw new ConflictException('Aucun lot en stock à inventorier.');
        }

        $inventaire->recomputeProgress();
        $this->entityManager->persist($inventaire);
        $this->entityManager->flush();

        return $this->getById((int) $inventaire->getId());
    }

    public function compterProduit(int $inventaireId, int $medicamentId, CompterInventaireProduitInput $input): InventairePharmacie
    {
        $this->assertValid($input);
        $inventaire = $this->requireEnCours($inventaireId);
        $saisies = $this->normalizeLignes($input->lignes);
        $saisiesParLigne = [];
        foreach ($saisies as $saisie) {
            $saisiesParLigne[$saisie->ligneId] = $saisie;
        }

        $lignesProduit = $this->requireLignesProduit($inventaire, $medicamentId);
        $this->appliquerCorrections($lignesProduit, $input->prixVente, $saisies);

        $aMarquer = [];
        if ([] === $saisiesParLigne) {
            foreach ($lignesProduit as $ligne) {
                if (!$ligne->isCompte()) {
                    $aMarquer[] = $ligne;
                }
            }
        } else {
            foreach ($lignesProduit as $ligne) {
                if (!array_key_exists((int) $ligne->getId(), $saisiesParLigne)) {
                    continue;
                }
                if ($ligne->isCompte()) {
                    throw new ConflictException(sprintf('Le lot %s est déjà marqué comme compté.', $ligne->getNumeroLot()));
                }
                $aMarquer[] = $ligne;
            }
            if ([] === $aMarquer) {
                throw new NotFoundException('Aucune ligne de ce produit ne correspond à la saisie.');
            }
        }

        if ([] === $aMarquer) {
            throw new ConflictException('Ce produit est déjà marqué comme compté.');
        }

        $personnel = $this->currentPersonnel();
        $now = $this->now();
        foreach ($aMarquer as $ligne) {
            $saisie = $saisiesParLigne[(int) $ligne->getId()] ?? null;
            $quantite = $saisie instanceof CompterInventaireLigneSaisieInput ? $saisie->quantiteComptee : null;
            $this->appliquerComptage($inventaire, $ligne, $quantite, $personnel, $now);
        }

        $inventaire->recomputeProgress();
        $this->flushLotUniqueness();

        return $this->getById($inventaireId);
    }

    public function compterLigne(int $inventaireId, int $ligneId, CompterInventaireLigneInput $input): InventairePharmacie
    {
        $this->assertValid($input);
        $inventaire = $this->requireEnCours($inventaireId);
        $ligne = $this->requireLigne($inventaire, $ligneId);
        if ($ligne->isCompte()) {
            throw new ConflictException(sprintf('Le lot %s est déjà marqué comme compté.', $ligne->getNumeroLot()));
        }

        $this->appliquerComptage($inventaire, $ligne, $input->quantiteComptee, $this->currentPersonnel(), $this->now());
        $inventaire->recomputeProgress();
        $this->entityManager->flush();

        return $this->getById($inventaireId);
    }

    public function corrigerProduit(int $inventaireId, int $medicamentId, CompterInventaireProduitInput $input): InventairePharmacie
    {
        $this->assertValid($input);
        $inventaire = $this->requireEnCours($inventaireId);
        $lignesProduit = $this->requireLignesProduit($inventaire, $medicamentId);
        $this->appliquerCorrections($lignesProduit, $input->prixVente, $this->normalizeLignes($input->lignes));
        $this->flushLotUniqueness();

        return $this->getById($inventaireId);
    }

    /**
     * @return list<string>
     */
    public function exportHeaders(): array
    {
        return ['N°', 'Code', 'Médicament', 'Forme', 'Dosage', 'Prix de vente', 'N° lot', 'Péremption', 'Qté ouverture', 'Qté actuelle', 'Qté comptée', 'Écart', 'Statut', 'Compté par'];
    }

    /**
     * @return list<list<string|null>>
     */
    public function buildExportRows(InventairePharmacie $inventaire): array
    {
        $detail = $this->serializeDetail($inventaire);
        $rows = [];
        foreach ($detail['produits'] as $produit) {
            $med = is_array($produit['medicament'] ?? null) ? $produit['medicament'] : [];
            foreach ($produit['lignes'] ?? [] as $ligne) {
                if (!is_array($ligne)) {
                    continue;
                }
                $comptePar = is_array($ligne['comptePar'] ?? null) ? $ligne['comptePar'] : null;
                $rows[] = [
                    (string) ($med['code'] ?? ''),
                    (string) ($med['libelle'] ?? ''),
                    (string) ($med['forme'] ?? ''),
                    (string) ($med['dosage'] ?? ''),
                    $this->formatPrixExport($med['prixVente'] ?? null),
                    (string) ($ligne['numeroLot'] ?? ''),
                    $this->formatDateExport($ligne['datePeremption'] ?? null),
                    (string) ($ligne['quantiteSysteme'] ?? ''),
                    (string) ($ligne['quantiteActuelle'] ?? ''),
                    null !== ($ligne['quantiteComptee'] ?? null) ? (string) $ligne['quantiteComptee'] : '',
                    null !== ($ligne['ecart'] ?? null) ? (string) $ligne['ecart'] : '',
                    !empty($ligne['compte']) ? 'Compté' : 'À compter',
                    $this->personnelExportLabel($comptePar),
                ];
            }
        }

        return $rows;
    }

    public function exportTitle(InventairePharmacie $inventaire): string
    {
        return sprintf('Fiche d\'inventaire %s — %s', $inventaire->getNumero(), $inventaire->getLibelle());
    }

    public function exportFilenamePrefix(InventairePharmacie $inventaire): string
    {
        $numero = preg_replace('/[^A-Za-z0-9\-]+/', '-', (string) $inventaire->getNumero()) ?: 'inventaire';

        return 'inventaire-' . strtolower($numero);
    }

    public function cloturer(int $id): InventairePharmacie
    {
        $inventaire = $this->requireEnCours($id);
        $inventaire->recomputeProgress();
        if ($inventaire->getLignesComptees() < $inventaire->getLignesCount()) {
            throw new ConflictException(sprintf(
                'Impossible de clôturer : %d lot(s) restent à marquer comme comptés.',
                $inventaire->getLignesCount() - $inventaire->getLignesComptees(),
            ));
        }

        $now = $this->now();
        $inventaire
            ->setStatut(InventairePharmacie::STATUT_CLOTURE)
            ->setDateCloture($now->setTime(0, 0))
            ->setClotureAt($now)
            ->setCloturePar($this->currentPersonnel());
        $this->entityManager->flush();

        return $this->getById($id);
    }

    public function delete(int $id): void
    {
        $inventaire = $this->requireEnCours($id);
        $inventaire->recomputeProgress();
        if ($inventaire->getLignesComptees() > 0) {
            throw new ConflictException('Impossible de supprimer une campagne déjà partiellement comptée. Terminez le comptage puis clôturez.');
        }

        $this->entityManager->remove($inventaire);
        $this->entityManager->flush();
    }

    public function getById(int $id): InventairePharmacie
    {
        $inventaire = $this->inventaireRepository->findWithLignes($id);
        if (!$inventaire instanceof InventairePharmacie) {
            throw new NotFoundException('Campagne d\'inventaire non trouvée.');
        }

        return $inventaire;
    }

    /** @return array<string, mixed> */
    public function serializeSummary(InventairePharmacie $inventaire): array
    {
        return [
            'id' => $inventaire->getId(),
            'numero' => $inventaire->getNumero(),
            'libelle' => $inventaire->getLibelle(),
            'notes' => $inventaire->getNotes(),
            'statut' => $inventaire->getStatut(),
            'dateDebut' => $inventaire->getDateDebut()?->format('Y-m-d'),
            'dateCloture' => $inventaire->getDateCloture()?->format('Y-m-d'),
            'lignesCount' => $inventaire->getLignesCount(),
            'lignesComptees' => $inventaire->getLignesComptees(),
            'produitsCount' => $inventaire->getProduitsCount(),
            'produitsComptes' => $inventaire->getProduitsComptes(),
            'createdAt' => $inventaire->getCreatedAt()?->format(\DateTimeInterface::ATOM),
            'clotureAt' => $inventaire->getClotureAt()?->format(\DateTimeInterface::ATOM),
            'createdBy' => $this->serializePersonnel($inventaire->getCreatedBy()),
            'cloturePar' => $this->serializePersonnel($inventaire->getCloturePar()),
        ];
    }

    /** @return array<string, mixed> */
    public function serializeDetail(InventairePharmacie $inventaire): array
    {
        $data = $this->serializeSummary($inventaire);
        $groupes = [];

        foreach ($inventaire->getLignes() as $ligne) {
            $medicament = $ligne->getMedicament();
            $medicamentId = $medicament?->getId() ?? 0;
            if (!isset($groupes[$medicamentId])) {
                $groupes[$medicamentId] = [
                    'medicament' => $medicament ? [
                        'id' => $medicament->getId(),
                        'code' => $medicament->getCode(),
                        'libelle' => $medicament->getLibelle(),
                        'dci' => $medicament->getDci(),
                        'forme' => $medicament->getForme(),
                        'dosage' => $medicament->getDosage(),
                        'prixVente' => $medicament->getPrixVente(),
                    ] : null,
                    'compte' => true,
                    'lignesCount' => 0,
                    'lignesComptees' => 0,
                    'quantiteSysteme' => 0,
                    'quantiteActuelle' => 0,
                    'quantiteComptee' => 0,
                    'lignes' => [],
                ];
            }

            $serialized = $this->serializeLigne($ligne);
            $groupes[$medicamentId]['lignes'][] = $serialized;
            ++$groupes[$medicamentId]['lignesCount'];
            $groupes[$medicamentId]['quantiteSysteme'] += $serialized['quantiteSysteme'];
            $groupes[$medicamentId]['quantiteActuelle'] += $serialized['quantiteActuelle'];
            if ($serialized['compte']) {
                ++$groupes[$medicamentId]['lignesComptees'];
                $groupes[$medicamentId]['quantiteComptee'] += (int) $serialized['quantiteComptee'];
            } else {
                $groupes[$medicamentId]['compte'] = false;
            }
        }

        foreach ($groupes as &$groupe) {
            usort(
                $groupe['lignes'],
                static fn (array $a, array $b): int => strcmp((string) $a['datePeremption'], (string) $b['datePeremption'])
                    ?: strcmp((string) $a['numeroLot'], (string) $b['numeroLot']),
            );
        }
        unset($groupe);

        $produits = array_values($groupes);
        usort(
            $produits,
            static function (array $a, array $b): int {
                $libA = (string) ($a['medicament']['libelle'] ?? '');
                $libB = (string) ($b['medicament']['libelle'] ?? '');

                return strcasecmp($libA, $libB) ?: strcasecmp((string) ($a['medicament']['code'] ?? ''), (string) ($b['medicament']['code'] ?? ''));
            },
        );

        $data['produits'] = $produits;

        return $data;
    }

    /** @return array<string, mixed> */
    private function serializeLigne(InventairePharmacieLigne $ligne): array
    {
        $lot = $ligne->getLot();
        $quantiteActuelle = $lot?->getQuantiteRestante() ?? 0;
        $quantiteComptee = $ligne->getQuantiteComptee();

        return [
            'id' => $ligne->getId(),
            'lotId' => $lot?->getId(),
            'numeroLot' => $ligne->getNumeroLot(),
            'datePeremption' => $ligne->getDatePeremption()?->format('Y-m-d'),
            'statutLot' => $lot?->getStatut(),
            'quantiteSysteme' => $ligne->getQuantiteSysteme(),
            'quantiteActuelle' => $quantiteActuelle,
            'quantiteComptee' => $quantiteComptee,
            'ecart' => null !== $quantiteComptee ? $quantiteComptee - $ligne->getQuantiteSysteme() : null,
            'compte' => $ligne->isCompte(),
            'compteAt' => $ligne->getCompteAt()?->format(\DateTimeInterface::ATOM),
            'comptePar' => $this->serializePersonnel($ligne->getComptePar()),
            'mouvementId' => $ligne->getMouvement()?->getId(),
        ];
    }

    /** @return array{id: string, nom: string|null, prenom: string|null}|null */
    private function serializePersonnel(?Personnel $personnel): ?array
    {
        if (null === $personnel) {
            return null;
        }

        return [
            'id' => $personnel->getId()?->toRfc4122(),
            'nom' => $personnel->getNom(),
            'prenom' => $personnel->getPrenom(),
        ];
    }

    private function appliquerComptage(
        InventairePharmacie $inventaire,
        InventairePharmacieLigne $ligne,
        ?int $quantiteSaisie,
        Personnel $personnel,
        \DateTimeImmutable $now,
    ): void {
        $lot = $ligne->getLot();
        if (null === $lot) {
            throw new ConflictException(sprintf('Le lot %s n\'existe plus.', $ligne->getNumeroLot()));
        }

        $quantiteActuelle = $lot->getQuantiteRestante();
        $quantiteComptee = $quantiteSaisie ?? $quantiteActuelle;
        if ($quantiteComptee < 0) {
            throw new ConflictException('La quantité comptée ne peut pas être négative.');
        }

        $delta = $quantiteComptee - $quantiteActuelle;
        $mouvement = null;
        $motif = sprintf(
            'Inventaire %s — %s',
            $inventaire->getNumero(),
            $inventaire->getLibelle(),
        );

        if ($delta > 0) {
            $mouvement = $this->stockService->applyEntree(
                $lot,
                $delta,
                MouvementStock::TYPE_AJUSTEMENT_PLUS,
                MouvementStock::DOC_INVENTAIRE,
                (int) $inventaire->getId(),
            );
            $mouvement->setMotif($motif);
        } elseif ($delta < 0) {
            $mouvement = $this->stockService->applySortie(
                $lot,
                abs($delta),
                MouvementStock::TYPE_AJUSTEMENT_MOINS,
                MouvementStock::DOC_INVENTAIRE,
                (int) $inventaire->getId(),
                false,
            );
            $mouvement->setMotif($motif);
        }

        $ligne
            ->setQuantiteComptee($quantiteComptee)
            ->setCompte(true)
            ->setCompteAt($now)
            ->setComptePar($personnel)
            ->setMouvement($mouvement);
    }

    private function requireEnCours(int $id): InventairePharmacie
    {
        $inventaire = $this->getById($id);
        if (!$inventaire->isEnCours()) {
            throw new ConflictException('Cette campagne d\'inventaire est déjà clôturée.');
        }

        return $inventaire;
    }

    /**
     * @return list<InventairePharmacieLigne>
     */
    private function requireLignesProduit(InventairePharmacie $inventaire, int $medicamentId): array
    {
        $lignesProduit = [];
        foreach ($inventaire->getLignes() as $ligne) {
            if ($ligne->getMedicament()?->getId() === $medicamentId) {
                $lignesProduit[] = $ligne;
            }
        }
        if ([] === $lignesProduit) {
            throw new NotFoundException('Ce médicament n\'est pas dans la campagne.');
        }

        return $lignesProduit;
    }

    /**
     * @param list<InventairePharmacieLigne> $lignesProduit
     * @param list<CompterInventaireLigneSaisieInput> $saisies
     */
    private function appliquerCorrections(array $lignesProduit, ?string $prixVente, array $saisies): void
    {
        if (null !== $prixVente) {
            $medicament = $lignesProduit[0]->getMedicament();
            if (null === $medicament) {
                throw new ConflictException('Médicament introuvable pour cette ligne d\'inventaire.');
            }
            $medicament->setPrixVente($this->stockService->normalizePrix($prixVente));
        }

        $parId = [];
        foreach ($lignesProduit as $ligne) {
            $parId[(int) $ligne->getId()] = $ligne;
        }

        foreach ($saisies as $saisie) {
            $hasNumero = null !== $saisie->numeroLot && '' !== $saisie->numeroLot;
            $hasDate = null !== $saisie->datePeremption && '' !== $saisie->datePeremption;
            if (!$hasNumero && !$hasDate) {
                continue;
            }
            $ligne = $parId[$saisie->ligneId] ?? null;
            if (!$ligne instanceof InventairePharmacieLigne) {
                throw new NotFoundException('Ligne d\'inventaire non trouvée pour ce produit.');
            }
            $lot = $ligne->getLot();
            if (null === $lot) {
                throw new ConflictException(sprintf('Le lot %s n\'existe plus.', $ligne->getNumeroLot()));
            }
            if ($hasNumero) {
                $this->appliquerNumeroLot($lot, $ligne, $saisie->numeroLot);
            }
            if ($hasDate) {
                $date = $this->stockService->parseDate($saisie->datePeremption, 'Date de péremption');
                $lot->setDatePeremption($date);
                $ligne->setDatePeremption($date);
                $this->stockService->refreshStatut($lot);
            }
        }
    }

    private function appliquerNumeroLot(\App\Entity\Lot $lot, InventairePharmacieLigne $ligne, string $numeroLot): void
    {
        $numero = strtoupper(trim($numeroLot));
        if ('' === $numero) {
            throw new ConflictException('Le numéro de lot est obligatoire.');
        }
        $medicament = $lot->getMedicament();
        if (null === $medicament) {
            throw new ConflictException('Lot sans médicament.');
        }
        $existing = $this->lotRepository->findOneByMedicamentAndNumero($medicament, $numero);
        if (null !== $existing && $existing->getId() !== $lot->getId()) {
            throw new ConflictException('Ce numéro de lot existe déjà pour ce médicament.');
        }
        $lot->setNumeroLot($numero);
        $ligne->setNumeroLot($numero);
    }

    private function flushLotUniqueness(): void
    {
        try {
            $this->entityManager->flush();
        } catch (UniqueConstraintViolationException) {
            throw new ConflictException('Ce numéro de lot existe déjà pour ce médicament.');
        }
    }

    /**
     * @param list<CompterInventaireLigneSaisieInput|array<string, mixed>> $lignes
     * @return list<CompterInventaireLigneSaisieInput>
     */
    private function normalizeLignes(array $lignes): array
    {
        $normalized = [];
        foreach ($lignes as $ligne) {
            if ($ligne instanceof CompterInventaireLigneSaisieInput) {
                $normalized[] = $ligne;
                continue;
            }
            $quantite = $ligne['quantiteComptee'] ?? null;
            $datePeremption = $ligne['datePeremption'] ?? null;
            $numeroLot = $ligne['numeroLot'] ?? null;
            $normalized[] = new CompterInventaireLigneSaisieInput(
                (int) ($ligne['ligneId'] ?? 0),
                null === $quantite || '' === $quantite ? null : (int) $quantite,
                null === $datePeremption || '' === $datePeremption ? null : (string) $datePeremption,
                null === $numeroLot || '' === $numeroLot ? null : (string) $numeroLot,
            );
        }

        return $normalized;
    }

    private function formatPrixExport(mixed $value): string
    {
        if (null === $value || '' === $value) {
            return '';
        }

        return number_format((float) $value, 2, ',', ' ') . ' FC';
    }

    private function formatDateExport(mixed $value): string
    {
        $raw = trim((string) $value);
        if ('' === $raw) {
            return '';
        }
        $iso = substr($raw, 0, 10);
        $parts = explode('-', $iso);
        if (3 !== count($parts)) {
            return $raw;
        }

        return $parts[2] . '/' . $parts[1] . '/' . $parts[0];
    }

    /** @param array{nom?: string|null, prenom?: string|null}|null $personnel */
    private function personnelExportLabel(?array $personnel): string
    {
        if (null === $personnel) {
            return '';
        }
        $parts = array_filter([
            trim((string) ($personnel['prenom'] ?? '')),
            trim((string) ($personnel['nom'] ?? '')),
        ]);

        return implode(' ', $parts);
    }

    private function requireLigne(InventairePharmacie $inventaire, int $ligneId): InventairePharmacieLigne
    {
        foreach ($inventaire->getLignes() as $ligne) {
            if ($ligne->getId() === $ligneId) {
                return $ligne;
            }
        }

        throw new NotFoundException('Ligne d\'inventaire non trouvée.');
    }

    private function currentPersonnel(): Personnel
    {
        $user = $this->security->getUser();
        if (!$user instanceof Personnel) {
            throw new ConflictException('Utilisateur non identifié.');
        }

        return $user;
    }

    private function nextNumero(\DateTimeImmutable $now): string
    {
        $prefix = 'INV-' . $now->format('Ymd') . '-';

        return $this->inventaireRepository->nextNumeroForPrefix($prefix);
    }

    private function now(): \DateTimeImmutable
    {
        return new \DateTimeImmutable('now', new \DateTimeZone(CalendarDate::TIMEZONE));
    }

    private function nullable(?string $value): ?string
    {
        $trimmed = trim((string) $value);

        return '' === $trimmed ? null : $trimmed;
    }

    private function assertValid(object $value): void
    {
        $errors = $this->validator->validate($value);
        if (count($errors) > 0) {
            throw new ValidationFailedException($value, $errors);
        }
    }
}
