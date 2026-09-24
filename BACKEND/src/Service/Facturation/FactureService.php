<?php

namespace App\Service\Facturation;

use App\DTO\Common\PaginatedResult;
use App\DTO\Facturation\FactureLigneInput;
use App\DTO\Facturation\FactureListQuery;
use App\DTO\Facturation\UpsertFactureInput;
use App\Entity\ActeFinancier;
use App\Entity\CategorieTarifaire;
use App\Entity\Facture;
use App\Entity\FactureLigne;
use App\Entity\Patient;
use App\Entity\Structure;
use App\Exception\ConflictException;
use App\Exception\NotFoundException;
use App\Repository\ActeFinancierRepository;
use App\Repository\FactureRepository;
use App\Repository\PatientRepository;
use App\Repository\StructureRepository;
use App\Security\Permission\FacturationPermissions;
use App\Util\CalendarDate;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Exception\ValidationFailedException;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final class FactureService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly FactureRepository $factureRepository,
        private readonly PatientRepository $patientRepository,
        private readonly StructureRepository $structureRepository,
        private readonly ActeFinancierRepository $acteFinancierRepository,
        private readonly ValidatorInterface $validator,
        private readonly Security $security,
    ) {
    }

    public function paginate(FactureListQuery $query): PaginatedResult
    {
        $this->assertValid($query);
        $result = $this->factureRepository->searchPaginated($query->page, $query->limit, [
            'search' => $query->search,
            'statut' => $query->statut,
            'categorieTarifaire' => $query->categorieTarifaire,
            'structureId' => $query->structureId,
            'dateFrom' => $query->dateFrom,
            'dateTo' => $query->dateTo,
        ]);

        return new PaginatedResult(
            array_map([$this, 'serializeSummary'], $result['items']),
            $query->page,
            $query->limit,
            $result['total'],
        );
    }

    public function getById(int $id): Facture
    {
        $facture = $this->factureRepository->find($id);
        if (null === $facture) {
            throw new NotFoundException('Facture non trouvée.');
        }

        return $facture;
    }

    public function create(UpsertFactureInput $input): Facture
    {
        $this->assertValid($input);
        $today = (new \DateTimeImmutable('now', new \DateTimeZone(CalendarDate::TIMEZONE)))->format('Ymd');
        $facture = (new Facture())
            ->setNumero($this->factureRepository->nextNumeroForPrefix('FAC-' . $today . '-'))
            ->setStatut(Facture::STATUT_BROUILLON)
            ->setCreatedAt(new \DateTimeImmutable());
        $this->apply($facture, $input);
        $this->entityManager->persist($facture);
        $this->entityManager->flush();

        return $facture;
    }

    public function update(int $id, UpsertFactureInput $input): Facture
    {
        $this->assertValid($input);
        $facture = $this->requireBrouillon($id);
        $this->apply($facture, $input);
        $this->entityManager->flush();

        return $facture;
    }

    public function valider(int $id): Facture
    {
        $facture = $this->requireBrouillon($id);
        if ($facture->getLignes()->isEmpty()) {
            throw new ConflictException('Ajoutez au moins un acte avant de valider.');
        }

        $facture->setStatut(Facture::STATUT_VALIDEE);
        $this->entityManager->flush();

        return $facture;
    }

    public function annuler(int $id): Facture
    {
        $facture = $this->getById($id);
        if (Facture::STATUT_ANNULEE === $facture->getStatut()) {
            throw new ConflictException('Cette facture est déjà annulée.');
        }
        if (Facture::STATUT_BROUILLON === $facture->getStatut()) {
            throw new ConflictException('Supprimez le brouillon plutôt que de l\'annuler.');
        }

        $facture->setStatut(Facture::STATUT_ANNULEE);
        $this->entityManager->flush();

        return $facture;
    }

    public function delete(int $id): void
    {
        $facture = $this->requireBrouillon($id);
        $this->entityManager->remove($facture);
        $this->entityManager->flush();
    }

    /** @return array<string, mixed> */
    public function serializeSummary(Facture $facture): array
    {
        $patient = $facture->getPatient();
        $structure = $facture->getStructure();

        return [
            'id' => $facture->getId(),
            'numero' => $facture->getNumero(),
            'dateFacture' => $facture->getDateFacture()?->format('Y-m-d'),
            'categorieTarifaire' => $facture->getCategorieTarifaire(),
            'numeroAffiliation' => $facture->getNumeroAffiliation(),
            'statut' => $facture->getStatut(),
            'montantBrut' => $facture->getMontantBrut(),
            'remiseType' => $facture->getRemiseType(),
            'remiseValeur' => $facture->getRemiseValeur(),
            'remiseMontant' => $facture->getRemiseMontant(),
            'montantTotal' => $facture->getMontantTotal(),
            'lignesCount' => $facture->getLignes()->count(),
            'notes' => $facture->getNotes(),
            'patientId' => $patient?->getId()?->__toString(),
            'patient' => $patient instanceof Patient ? [
                'id' => (string) $patient->getId(),
                'nom' => $patient->getNom(),
                'postNom' => $patient->getPostNom(),
                'prenom' => $patient->getPrenom(),
                'fullName' => $patient->getFullName(),
                'telephone' => $patient->getTelephone(),
                'codeUkv' => $patient->getCodeUkv(),
                'numDossier' => $patient->getDpi()?->getNumDossier(),
                'categorieTarifaire' => $patient->getCategorieTarifaire(),
                'numeroAffiliation' => $patient->getNumeroAffiliation(),
                'structure' => $this->serializeStructure($patient->getStructure()),
            ] : null,
            'structureId' => $structure?->getId(),
            'structure' => $this->serializeStructure($structure),
            'createdAt' => $facture->getCreatedAt()?->format(\DateTimeInterface::ATOM),
        ];
    }

    /** @return array<string, mixed> */
    public function serializeDetail(Facture $facture): array
    {
        return [
            ...$this->serializeSummary($facture),
            'lignes' => array_map(
                fn (FactureLigne $ligne): array => $this->serializeLigne($ligne),
                $facture->getLignes()->toArray(),
            ),
        ];
    }

    private function apply(Facture $facture, UpsertFactureInput $input): void
    {
        $patient = $this->requirePatient($input->patientId);
        $categorie = $this->normalizeCategorie($input->categorieTarifaire);
        $structure = $this->resolveStructure($categorie, $input->structureId);

        $date = \DateTime::createFromFormat('Y-m-d', $input->dateFacture);
        if (false === $date) {
            throw new ConflictException('Date de facture invalide.');
        }

        $canRemise = $this->security->isGranted(FacturationPermissions::FACTURE_REMISE);
        $this->assertRemisePermission($input, $canRemise);

        $preservedGlobal = null;
        $preservedLignes = [];
        if (!$canRemise && null !== $facture->getId()) {
            $preservedGlobal = [
                'type' => $facture->getRemiseType(),
                'valeur' => $facture->getRemiseValeur(),
            ];
            foreach ($facture->getLignes() as $existingLigne) {
                $acteId = $existingLigne->getActe()?->getId();
                if (null !== $acteId) {
                    $preservedLignes[$acteId] = [
                        'type' => $existingLigne->getRemiseType(),
                        'valeur' => $existingLigne->getRemiseValeur(),
                    ];
                }
            }
        }

        $facture
            ->setPatient($patient)
            ->setDateFacture($date)
            ->setCategorieTarifaire($categorie)
            ->setStructure($structure)
            ->setNumeroAffiliation($this->nullable($input->numeroAffiliation))
            ->setNotes($this->nullable($input->notes));

        $this->replaceLignes($facture, $input, $categorie, $canRemise, $preservedGlobal, $preservedLignes);
    }

    /**
     * @param array{type: string, valeur: string}|null $preservedGlobal
     * @param array<int, array{type: string, valeur: string}> $preservedLignes
     */
    private function replaceLignes(
        Facture $facture,
        UpsertFactureInput $input,
        string $categorie,
        bool $canRemise,
        ?array $preservedGlobal,
        array $preservedLignes,
    ): void {
        $facture->clearLignes();
        $montantBrut = '0.00';
        $sousTotal = '0.00';

        foreach ($this->normalizeLignes($input->lignes) as $ligneInput) {
            $acte = $this->acteFinancierRepository->find($ligneInput->acteId);
            if (!$acte instanceof ActeFinancier) {
                throw new NotFoundException('Acte tarifaire non trouvé.');
            }
            if (ActeFinancier::STATUT_ACTIF !== $acte->getStatut()) {
                throw new ConflictException(sprintf('L\'acte « %s » est inactif.', $acte->getLibelle()));
            }

            $quantite = max(1, $ligneInput->quantite);
            $unitaire = RemiseCalculator::money($acte->tarifPour($categorie));
            $tarifBrut = RemiseCalculator::money((string) ((float) $unitaire * $quantite));
            $remiseSource = (!$canRemise && isset($preservedLignes[$ligneInput->acteId]))
                ? $preservedLignes[$ligneInput->acteId]
                : ['type' => $ligneInput->remiseType, 'valeur' => $ligneInput->remiseValeur];
            $remise = RemiseCalculator::compute($tarifBrut, $remiseSource['type'], $remiseSource['valeur']);
            $ligneTotal = RemiseCalculator::money((string) ((float) $tarifBrut - (float) $remise['montant']));

            $ligne = (new FactureLigne())
                ->setActe($acte)
                ->setCodeActe((string) $acte->getCode())
                ->setLibelle((string) $acte->getLibelle())
                ->setServiceGrille($acte->getServiceGrille())
                ->setQuantite($quantite)
                ->setTarifUnitaire($unitaire)
                ->setTarifBrut($tarifBrut)
                ->setRemiseType($remise['type'])
                ->setRemiseValeur($remise['valeur'])
                ->setRemiseMontant($remise['montant'])
                ->setTarifTotal($ligneTotal);

            $facture->addLigne($ligne);
            $montantBrut = RemiseCalculator::money((string) ((float) $montantBrut + (float) $tarifBrut));
            $sousTotal = RemiseCalculator::money((string) ((float) $sousTotal + (float) $ligneTotal));
        }

        $globalSource = null !== $preservedGlobal
            ? $preservedGlobal
            : ['type' => $input->remiseType, 'valeur' => $input->remiseValeur];
        $global = RemiseCalculator::compute($sousTotal, $globalSource['type'], $globalSource['valeur']);

        $facture
            ->setMontantBrut($montantBrut)
            ->setRemiseType($global['type'])
            ->setRemiseValeur($global['valeur'])
            ->setRemiseMontant($global['montant'])
            ->setMontantTotal(RemiseCalculator::money((string) ((float) $sousTotal - (float) $global['montant'])));
    }

    /**
     * @param list<FactureLigneInput|array<string, mixed>> $lignes
     * @return list<FactureLigneInput>
     */
    private function normalizeLignes(array $lignes): array
    {
        $normalized = [];
        foreach ($lignes as $ligne) {
            if ($ligne instanceof FactureLigneInput) {
                $normalized[] = $ligne;
                continue;
            }

            $normalized[] = new FactureLigneInput(
                (int) ($ligne['acteId'] ?? 0),
                (int) ($ligne['quantite'] ?? 0),
                (string) ($ligne['remiseType'] ?? Facture::REMISE_NONE),
                (string) ($ligne['remiseValeur'] ?? '0'),
            );
        }

        return $normalized;
    }

    private function assertRemisePermission(UpsertFactureInput $input, bool $canRemise): void
    {
        if ($canRemise) {
            return;
        }

        if (RemiseCalculator::isActive($input->remiseType, $input->remiseValeur)) {
            throw new ConflictException('Vous n\'avez pas le droit d\'appliquer une remise.');
        }

        foreach ($this->normalizeLignes($input->lignes) as $ligne) {
            if (RemiseCalculator::isActive($ligne->remiseType, $ligne->remiseValeur)) {
                throw new ConflictException('Vous n\'avez pas le droit d\'appliquer une remise.');
            }
        }
    }

    private function requireBrouillon(int $id): Facture
    {
        $facture = $this->getById($id);
        if (!$facture->isBrouillon()) {
            throw new ConflictException('Seuls les brouillons peuvent être modifiés ou supprimés.');
        }

        return $facture;
    }

    private function requirePatient(string $id): Patient
    {
        try {
            $uuid = Uuid::fromString($id);
        } catch (\InvalidArgumentException) {
            throw new ConflictException('Identifiant patient invalide.');
        }

        $patient = $this->patientRepository->find($uuid);
        if (!$patient instanceof Patient) {
            throw new NotFoundException('Patient non trouvé.');
        }

        return $patient;
    }

    private function normalizeCategorie(string $categorieTarifaire): string
    {
        $categorie = CategorieTarifaire::normalize($categorieTarifaire);
        if (!CategorieTarifaire::isValid($categorie)) {
            throw new ConflictException('Catégorie tarifaire invalide.');
        }

        return $categorie;
    }

    private function resolveStructure(string $categorie, ?int $structureId): ?Structure
    {
        $structure = null;
        if (null !== $structureId) {
            $structure = $this->structureRepository->find($structureId);
            if (!$structure instanceof Structure) {
                throw new NotFoundException('Structure non trouvée.');
            }
            if (!$structure->isActif()) {
                throw new ConflictException('Cette structure est inactive.');
            }
        }

        if (CategorieTarifaire::requiresStructure($categorie)) {
            if (null === $structure) {
                throw new ConflictException('Une structure est obligatoire pour cette catégorie.');
            }
            $allowed = CategorieTarifaire::allowedStructureTypes($categorie);
            if (!in_array((string) $structure->getType(), $allowed, true)) {
                throw new ConflictException('Le type de structure ne correspond pas à la catégorie choisie.');
            }
        } elseif (null !== $structure) {
            throw new ConflictException('Cette catégorie ne nécessite pas de structure.');
        }

        return $structure;
    }

    /** @return array<string, mixed> */
    private function serializeLigne(FactureLigne $ligne): array
    {
        $acte = $ligne->getActe();

        return [
            'id' => $ligne->getId(),
            'acteId' => $acte?->getId(),
            'codeActe' => $ligne->getCodeActe(),
            'libelle' => $ligne->getLibelle(),
            'serviceGrille' => $ligne->getServiceGrille(),
            'quantite' => $ligne->getQuantite(),
            'tarifUnitaire' => $ligne->getTarifUnitaire(),
            'tarifBrut' => $ligne->getTarifBrut(),
            'remiseType' => $ligne->getRemiseType(),
            'remiseValeur' => $ligne->getRemiseValeur(),
            'remiseMontant' => $ligne->getRemiseMontant(),
            'tarifTotal' => $ligne->getTarifTotal(),
            'acte' => $acte instanceof ActeFinancier ? [
                'id' => $acte->getId(),
                'code' => $acte->getCode(),
                'libelle' => $acte->getLibelle(),
                'serviceGrille' => $acte->getServiceGrille(),
                'tarifA0' => $acte->getTarifA0(),
                'tarifA1' => $acte->getTarifA1(),
                'tarifA' => $acte->getTarif(),
                'tarifB' => $acte->getTarifB(),
                'tarifC' => $acte->getTarifC(),
            ] : null,
        ];
    }

    /** @return array{id: int, code: string, libelle: string, type: string, statut: string}|null */
    private function serializeStructure(?Structure $structure): ?array
    {
        if (!$structure instanceof Structure) {
            return null;
        }

        return [
            'id' => $structure->getId(),
            'code' => (string) $structure->getCode(),
            'libelle' => (string) $structure->getLibelle(),
            'type' => (string) $structure->getType(),
            'statut' => (string) $structure->getStatut(),
        ];
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
