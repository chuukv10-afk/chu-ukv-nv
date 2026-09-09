<?php

namespace App\Service\Intendance;

use App\DTO\Common\PaginatedResult;
use App\DTO\Intendance\BienListQuery;
use App\DTO\Intendance\CreateGroupeBienInput;
use App\DTO\Intendance\ProposerCodeQuery;
use App\DTO\Intendance\ReformerBienInput;
use App\DTO\Intendance\TransfererBienInput;
use App\DTO\Intendance\UpsertBienInput;
use App\Entity\BienPatrimonial;
use App\Entity\HistoriqueBien;
use App\Entity\LocalIntendance;
use App\Entity\Personnel;
use App\Entity\Service;
use App\Entity\TypeBien;
use App\Exception\ConflictException;
use App\Exception\NotFoundException;
use App\Repository\BienPatrimonialRepository;
use App\Repository\HistoriqueBienRepository;
use App\Repository\LocalIntendanceRepository;
use App\Repository\ServiceRepository;
use App\Repository\TypeBienRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Validator\Exception\ValidationFailedException;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final class BienPatrimonialService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly BienPatrimonialRepository $bienPatrimonialRepository,
        private readonly HistoriqueBienRepository $historiqueBienRepository,
        private readonly TypeBienRepository $typeBienRepository,
        private readonly ServiceRepository $serviceRepository,
        private readonly LocalIntendanceRepository $localIntendanceRepository,
        private readonly InventaireCodeGenerator $codeGenerator,
        private readonly ValidatorInterface $validator,
        private readonly Security $security,
    ) {
    }

    public function paginate(BienListQuery $query): PaginatedResult
    {
        $this->assertValid($query);
        $result = $this->bienPatrimonialRepository->paginate($query);

        return new PaginatedResult(
            array_map([$this, 'serializeSummary'], $result['items']),
            $query->page,
            $query->limit,
            $result['total'],
        );
    }

    /** @return list<array<string, mixed>> */
    public function listExport(BienListQuery $query): array
    {
        $this->assertValid($query);

        return array_map([$this, 'serializeSummary'], $this->bienPatrimonialRepository->findFiltered($query));
    }

    /**
     * @return list<string>
     */
    public function exportHeaders(): array
    {
        return ['N°', 'Code', 'Type', 'Famille', 'Service', 'Local', 'État', 'Marque', 'Modèle', 'N° série', 'Date acquisition'];
    }

    /**
     * @return list<list<string|null>>
     */
    public function buildExportRows(BienListQuery $query): array
    {
        $this->assertValid($query);
        $rows = [];
        foreach ($this->bienPatrimonialRepository->findFiltered($query) as $bien) {
            $rows[] = [
                $bien->getCodeInventaire(),
                $bien->getType()?->getLibelle(),
                $bien->getFamille()?->getCode(),
                $bien->getService() ? $bien->getService()->getCode() . ' — ' . $bien->getService()->getLibelle() : null,
                $bien->getLocal()?->getLibelle(),
                $this->etatLabel((string) $bien->getEtat()),
                $bien->getMarque(),
                $bien->getModele(),
                $bien->getNumeroSerie(),
                $bien->getDateAcquisition()?->format('d/m/Y'),
            ];
        }

        return $rows;
    }

    public function getByCode(string $code): BienPatrimonial
    {
        $bien = $this->bienPatrimonialRepository->findByCodeInventaire($code);
        if (null === $bien) {
            throw new NotFoundException('Aucun bien trouvé pour ce code inventaire.');
        }

        return $bien;
    }

    /**
     * @param list<int> $ids
     * @return list<BienPatrimonial>
     */
    public function findActifsByIds(array $ids): array
    {
        if (count($ids) > 200) {
            throw new ConflictException('Vous pouvez générer au plus 200 étiquettes à la fois.');
        }
        if ([] === $ids) {
            throw new ConflictException('Sélectionnez au moins un bien.');
        }

        $items = $this->bienPatrimonialRepository->findActifsByIds($ids);
        if ([] === $items) {
            throw new NotFoundException('Aucun bien actif trouvé pour cette sélection.');
        }

        return $items;
    }

    /** @return array<string, mixed> */
    public function proposerCodes(ProposerCodeQuery $query): array
    {
        $this->assertValid($query);
        $type = $this->requireType($query->typeId, true);
        $service = $this->requireService($query->serviceId);
        $codes = $this->codeGenerator->proposer($service, $type->getFamille() ?? throw new ConflictException('Type sans famille.'), $query->count);

        return [
            'codes' => $codes,
            'code' => $codes[0] ?? null,
        ];
    }

    public function create(UpsertBienInput $input): BienPatrimonial
    {
        $this->assertValid($input);
        $type = $this->requireType($input->typeId, true);
        $famille = $type->getFamille() ?? throw new ConflictException('Type sans famille.');
        $service = $this->requireService($input->serviceId);
        $local = $this->resolveLocal($input->localId, $service);
        $code = $this->resolveCode($input->codeInventaire, $service, $famille);

        $bien = (new BienPatrimonial())
            ->setCodeInventaire($code)
            ->setType($type)
            ->setFamille($famille)
            ->setService($service)
            ->setLocal($local)
            ->setPrecision($this->trimOrNull($input->precision))
            ->setMarque($this->trimOrNull($input->marque))
            ->setModele($this->trimOrNull($input->modele))
            ->setNumeroSerie($this->trimOrNull($input->numeroSerie))
            ->setComplementLocalisation($this->trimOrNull($input->complementLocalisation))
            ->setEtat($this->normalizeEtat($input->etat))
            ->setDateAcquisition($this->parseDate($input->dateAcquisition))
            ->setObservation($this->trimOrNull($input->observation))
            ->setCreatedAt(new \DateTimeImmutable())
            ->setCreatedBy($this->currentPersonnel());

        $this->entityManager->persist($bien);
        $this->entityManager->flush();

        $this->record($bien, HistoriqueBien::TYPE_CREATION, motif: 'Création de la fiche');

        return $bien;
    }

    /**
     * @return list<BienPatrimonial>
     */
    public function createGroupe(CreateGroupeBienInput $input): array
    {
        $this->assertValid($input);
        $type = $this->requireType($input->typeId, true);
        $famille = $type->getFamille() ?? throw new ConflictException('Type sans famille.');
        $service = $this->requireService($input->serviceId);
        $local = $this->resolveLocal($input->localId, $service);

        $proposed = $this->codeGenerator->proposer($service, $famille, $input->copies);
        $codes = [];
        for ($i = 0; $i < $input->copies; ++$i) {
            $override = isset($input->codes[$i]) ? (string) $input->codes[$i] : null;
            $codes[] = $this->resolveCode($override, $service, $famille, $proposed[$i] ?? null, $codes);
        }

        $now = new \DateTimeImmutable();
        $author = $this->currentPersonnel();
        $biens = [];

        foreach ($codes as $code) {
            $bien = (new BienPatrimonial())
                ->setCodeInventaire($code)
                ->setType($type)
                ->setFamille($famille)
                ->setService($service)
                ->setLocal($local)
                ->setPrecision($this->trimOrNull($input->precision))
                ->setMarque($this->trimOrNull($input->marque))
                ->setModele($this->trimOrNull($input->modele))
                ->setComplementLocalisation($this->trimOrNull($input->complementLocalisation))
                ->setEtat($this->normalizeEtat($input->etat))
                ->setDateAcquisition($this->parseDate($input->dateAcquisition))
                ->setObservation($this->trimOrNull($input->observation))
                ->setCreatedAt($now)
                ->setCreatedBy($author);
            $this->entityManager->persist($bien);
            $biens[] = $bien;
        }

        $this->entityManager->flush();

        foreach ($biens as $bien) {
            $this->record($bien, HistoriqueBien::TYPE_CREATION, motif: sprintf('Création groupée (%d)', $input->copies), flush: false);
        }
        $this->entityManager->flush();

        return $biens;
    }

    public function update(int $id, UpsertBienInput $input): BienPatrimonial
    {
        $this->assertValid($input);
        $bien = $this->getById($id);
        if ($bien->isSupprime()) {
            throw new ConflictException('Cette fiche a été supprimée.');
        }

        $type = $this->requireType($input->typeId, false);
        $famille = $type->getFamille() ?? throw new ConflictException('Type sans famille.');
        $code = $this->resolveCode($input->codeInventaire, $bien->getService() ?? $this->requireService($input->serviceId), $famille, ignoreBien: $bien);

        $etatAvant = $bien->getEtat();
        $codeAvant = $bien->getCodeInventaire();
        $localAvant = $bien->getLocal();
        $typeChange = $bien->getType()?->getId() !== $type->getId();

        $bien
            ->setType($type)
            ->setFamille($famille)
            ->setCodeInventaire($code)
            ->setPrecision($this->trimOrNull($input->precision))
            ->setMarque($this->trimOrNull($input->marque))
            ->setModele($this->trimOrNull($input->modele))
            ->setNumeroSerie($this->trimOrNull($input->numeroSerie))
            ->setComplementLocalisation($this->trimOrNull($input->complementLocalisation))
            ->setEtat($this->normalizeEtat($input->etat, allowReforme: true))
            ->setDateAcquisition($this->parseDate($input->dateAcquisition))
            ->setObservation($this->trimOrNull($input->observation))
            ->setUpdatedAt(new \DateTimeImmutable())
            ->setUpdatedBy($this->currentPersonnel());

        $local = $this->resolveLocal($input->localId, $bien->getService());
        $bien->setLocal($local);

        $this->entityManager->flush();

        if ($codeAvant !== $code) {
            $this->record($bien, HistoriqueBien::TYPE_MODIFICATION_CODE, codeAvant: $codeAvant, codeApres: $code);
        }
        if ($etatAvant !== $bien->getEtat()) {
            $typeHist = BienPatrimonial::ETAT_R === $bien->getEtat() ? HistoriqueBien::TYPE_REFORME : HistoriqueBien::TYPE_CHANGEMENT_ETAT;
            $this->record($bien, $typeHist, etatAvant: $etatAvant, etatApres: $bien->getEtat());
        }
        if ($localAvant?->getId() !== $local?->getId()) {
            $this->record($bien, HistoriqueBien::TYPE_CHANGEMENT_LOCAL, localAvant: $localAvant, localApres: $local);
        } elseif ($typeChange) {
            $this->record($bien, HistoriqueBien::TYPE_MODIFICATION, motif: 'Modification de la fiche');
        }

        return $bien;
    }

    public function transferer(int $id, TransfererBienInput $input): BienPatrimonial
    {
        $this->assertValid($input);
        $bien = $this->getById($id);
        if ($bien->isSupprime()) {
            throw new ConflictException('Cette fiche a été supprimée.');
        }
        if (BienPatrimonial::ETAT_R === $bien->getEtat()) {
            throw new ConflictException('Un bien réformé ne peut pas être transféré.');
        }

        $origine = $bien->getService();
        $destination = $this->requireService($input->serviceId);
        if ($origine?->getId() === $destination->getId()) {
            throw new ConflictException('Le service destination doit être différent.');
        }

        $localAvant = $bien->getLocal();
        $localApres = $this->resolveLocal($input->localId, $destination);

        $bien
            ->setService($destination)
            ->setLocal($localApres)
            ->setUpdatedAt(new \DateTimeImmutable())
            ->setUpdatedBy($this->currentPersonnel());

        $this->entityManager->flush();
        $this->record(
            $bien,
            HistoriqueBien::TYPE_TRANSFERT,
            serviceAvant: $origine,
            serviceApres: $destination,
            localAvant: $localAvant,
            localApres: $localApres,
            motif: trim($input->motif),
        );

        return $bien;
    }

    public function reformer(int $id, ReformerBienInput $input): BienPatrimonial
    {
        $this->assertValid($input);
        $bien = $this->getById($id);
        if ($bien->isSupprime()) {
            throw new ConflictException('Cette fiche a été supprimée.');
        }
        if (BienPatrimonial::ETAT_R === $bien->getEtat()) {
            throw new ConflictException('Ce bien est déjà réformé.');
        }

        $etatAvant = $bien->getEtat();
        $bien
            ->setEtat(BienPatrimonial::ETAT_R)
            ->setUpdatedAt(new \DateTimeImmutable())
            ->setUpdatedBy($this->currentPersonnel());
        $this->entityManager->flush();
        $this->record($bien, HistoriqueBien::TYPE_REFORME, etatAvant: $etatAvant, etatApres: BienPatrimonial::ETAT_R, motif: trim($input->motif));

        return $bien;
    }

    public function delete(int $id): void
    {
        $bien = $this->getById($id);
        if ($bien->isSupprime()) {
            throw new ConflictException('Cette fiche est déjà supprimée.');
        }
        if ($this->historiqueBienRepository->hasType($bien, HistoriqueBien::TYPE_TRANSFERT)) {
            throw new ConflictException('Ce bien a déjà été transféré. Utilisez la réforme.');
        }

        $bien
            ->setSupprimeAt(new \DateTimeImmutable())
            ->setUpdatedAt(new \DateTimeImmutable())
            ->setUpdatedBy($this->currentPersonnel());
        $this->entityManager->flush();
        $this->record($bien, HistoriqueBien::TYPE_SUPPRESSION, motif: 'Suppression (erreur de saisie)');
    }

    public function getById(int $id): BienPatrimonial
    {
        $bien = $this->bienPatrimonialRepository->find($id);
        if (null === $bien) {
            throw new NotFoundException('Bien patrimonial non trouvé.');
        }

        return $bien;
    }

    /** @return list<array<string, mixed>> */
    public function historique(int $id): array
    {
        $bien = $this->getById($id);

        return array_map([$this, 'serializeHistorique'], $this->historiqueBienRepository->findByBien($bien));
    }

    /** @return array<string, mixed> */
    public function effectifs(BienListQuery $query): array
    {
        $this->assertValid($query);
        $items = $this->bienPatrimonialRepository->findFiltered($query);
        $parEtat = [];
        $parFamille = [];
        $parType = [];
        $parLocal = [];
        $sansLocal = 0;
        $parcActif = 0;

        foreach (BienPatrimonial::getEtats() as $etat) {
            $parEtat[$etat] = 0;
        }

        foreach ($items as $bien) {
            $etat = (string) $bien->getEtat();
            $parEtat[$etat] = ($parEtat[$etat] ?? 0) + 1;
            if (BienPatrimonial::ETAT_R !== $etat) {
                ++$parcActif;
            }

            $famille = $bien->getFamille();
            if (null !== $famille) {
                $fid = (int) $famille->getId();
                if (!isset($parFamille[$fid])) {
                    $parFamille[$fid] = [
                        'id' => $fid,
                        'code' => $famille->getCode(),
                        'libelle' => $famille->getLibelle(),
                        'total' => 0,
                    ];
                }
                ++$parFamille[$fid]['total'];
            }

            $type = $bien->getType();
            if (null !== $type) {
                $tid = (int) $type->getId();
                if (!isset($parType[$tid])) {
                    $parType[$tid] = [
                        'id' => $tid,
                        'code' => $type->getCode(),
                        'libelle' => $type->getLibelle(),
                        'familleCode' => $type->getFamille()?->getCode(),
                        'total' => 0,
                    ];
                }
                ++$parType[$tid]['total'];
            }

            $local = $bien->getLocal();
            if (null === $local) {
                ++$sansLocal;
            } else {
                $lid = (int) $local->getId();
                if (!isset($parLocal[$lid])) {
                    $parLocal[$lid] = [
                        'id' => $lid,
                        'code' => $local->getCode(),
                        'libelle' => $local->getLibelle(),
                        'total' => 0,
                    ];
                }
                ++$parLocal[$lid]['total'];
            }
        }

        $parEtatRows = [];
        foreach ($parEtat as $etat => $total) {
            $parEtatRows[] = ['etat' => $etat, 'total' => $total];
        }

        return [
            'total' => count($items),
            'parcActif' => $parcActif,
            'sansLocal' => $sansLocal,
            'parEtat' => $parEtatRows,
            'parFamille' => array_values($parFamille),
            'parType' => array_values($parType),
            'parLocal' => array_values($parLocal),
        ];
    }

    /** @return list<array<string, mixed>> */
    public function listServices(): array
    {
        $services = $this->serviceRepository->findBy([], ['libelle' => 'ASC']);

        return array_map(static fn (Service $service): array => [
            'id' => $service->getId(),
            'code' => $service->getCode(),
            'libelle' => $service->getLibelle(),
        ], $services);
    }

    /** @return array<string, mixed> */
    public function serializeSummary(BienPatrimonial $bien): array
    {
        $type = $bien->getType();
        $famille = $bien->getFamille();
        $service = $bien->getService();
        $local = $bien->getLocal();

        return [
            'id' => $bien->getId(),
            'codeInventaire' => $bien->getCodeInventaire(),
            'precision' => $bien->getPrecision(),
            'marque' => $bien->getMarque(),
            'modele' => $bien->getModele(),
            'numeroSerie' => $bien->getNumeroSerie(),
            'complementLocalisation' => $bien->getComplementLocalisation(),
            'etat' => $bien->getEtat(),
            'dateAcquisition' => $bien->getDateAcquisition()?->format('Y-m-d'),
            'observation' => $bien->getObservation(),
            'type' => $type ? [
                'id' => $type->getId(),
                'code' => $type->getCode(),
                'libelle' => $type->getLibelle(),
            ] : null,
            'famille' => $famille ? [
                'id' => $famille->getId(),
                'code' => $famille->getCode(),
                'libelle' => $famille->getLibelle(),
            ] : null,
            'service' => $service ? [
                'id' => $service->getId(),
                'code' => $service->getCode(),
                'libelle' => $service->getLibelle(),
            ] : null,
            'local' => $local ? [
                'id' => $local->getId(),
                'code' => $local->getCode(),
                'libelle' => $local->getLibelle(),
            ] : null,
            'createdAt' => $bien->getCreatedAt()?->format(\DateTimeInterface::ATOM),
            'updatedAt' => $bien->getUpdatedAt()?->format(\DateTimeInterface::ATOM),
        ];
    }

    /** @return array<string, mixed> */
    public function serializeHistorique(HistoriqueBien $ligne): array
    {
        return [
            'id' => $ligne->getId(),
            'type' => $ligne->getType(),
            'etatAvant' => $ligne->getEtatAvant(),
            'etatApres' => $ligne->getEtatApres(),
            'codeAvant' => $ligne->getCodeAvant(),
            'codeApres' => $ligne->getCodeApres(),
            'motif' => $ligne->getMotif(),
            'serviceAvant' => $ligne->getServiceAvant() ? [
                'id' => $ligne->getServiceAvant()->getId(),
                'code' => $ligne->getServiceAvant()->getCode(),
                'libelle' => $ligne->getServiceAvant()->getLibelle(),
            ] : null,
            'serviceApres' => $ligne->getServiceApres() ? [
                'id' => $ligne->getServiceApres()->getId(),
                'code' => $ligne->getServiceApres()->getCode(),
                'libelle' => $ligne->getServiceApres()->getLibelle(),
            ] : null,
            'localAvant' => $ligne->getLocalAvant() ? [
                'id' => $ligne->getLocalAvant()->getId(),
                'libelle' => $ligne->getLocalAvant()->getLibelle(),
            ] : null,
            'localApres' => $ligne->getLocalApres() ? [
                'id' => $ligne->getLocalApres()->getId(),
                'libelle' => $ligne->getLocalApres()->getLibelle(),
            ] : null,
            'createdAt' => $ligne->getCreatedAt()?->format(\DateTimeInterface::ATOM),
            'createdBy' => $ligne->getCreatedBy() ? [
                'id' => $ligne->getCreatedBy()->getId(),
                'nom' => $ligne->getCreatedBy()->getNom(),
                'prenom' => $ligne->getCreatedBy()->getPrenom(),
            ] : null,
        ];
    }

    /**
     * @param list<string> $reserved
     */
    private function resolveCode(
        ?string $requested,
        Service $service,
        \App\Entity\FamilleBien $famille,
        ?string $fallback = null,
        array $reserved = [],
        ?BienPatrimonial $ignoreBien = null,
    ): string {
        $raw = $requested;
        if (null === $raw || '' === trim($raw)) {
            $raw = $fallback ?? $this->codeGenerator->proposer($service, $famille)[0];
        }

        $code = InventaireCodeGenerator::normalize($raw);
        if (!InventaireCodeGenerator::isValid($code)) {
            throw new ConflictException('Le code inventaire est invalide (5 à 40 caractères, A–Z 0–9 . _ -).');
        }

        if (in_array($code, array_map([InventaireCodeGenerator::class, 'normalize'], $reserved), true)) {
            throw new ConflictException(sprintf('Le code %s est en double dans le lot.', $code));
        }

        $existing = $this->bienPatrimonialRepository->findByCodeInventaire($code);
        if (null !== $existing && $existing->getId() !== $ignoreBien?->getId()) {
            throw new ConflictException(sprintf('Le code %s est déjà attribué.', $code));
        }

        return $code;
    }

    private function requireType(int $id, bool $mustBeActif): TypeBien
    {
        $type = $this->typeBienRepository->find($id);
        if (null === $type) {
            throw new NotFoundException('Type de bien non trouvé.');
        }
        if ($mustBeActif && TypeBien::STATUT_ACTIF !== $type->getStatut()) {
            throw new ConflictException('Ce type n’est plus actif.');
        }

        return $type;
    }

    private function requireService(int $id): Service
    {
        $service = $this->serviceRepository->find($id);
        if (null === $service) {
            throw new NotFoundException('Service non trouvé.');
        }

        return $service;
    }

    private function resolveLocal(?int $localId, ?Service $service): ?LocalIntendance
    {
        if (null === $localId || $localId <= 0) {
            return null;
        }
        $local = $this->localIntendanceRepository->find($localId);
        if (null === $local) {
            throw new NotFoundException('Local non trouvé.');
        }
        if (null !== $service && $local->getService()?->getId() !== $service->getId()) {
            throw new ConflictException('Le local n’appartient pas à ce service.');
        }

        return $local;
    }

    private function etatLabel(string $etat): string
    {
        return match ($etat) {
            BienPatrimonial::ETAT_F => 'Fonctionnel',
            BienPatrimonial::ETAT_FP => 'Fonctionnel avec réserve',
            BienPatrimonial::ETAT_P => 'En panne',
            BienPatrimonial::ETAT_HU => 'Hors usage',
            BienPatrimonial::ETAT_R => 'Réformé',
            BienPatrimonial::ETAT_M => 'Manquant',
            default => $etat,
        };
    }

    private function normalizeEtat(string $etat, bool $allowReforme = false): string
    {
        $normalized = strtoupper(trim($etat));
        if ('HU' === $normalized) {
            $normalized = BienPatrimonial::ETAT_HU;
        }
        if (!in_array($normalized, BienPatrimonial::getEtats(), true)) {
            throw new ConflictException('État invalide.');
        }
        if (!$allowReforme && BienPatrimonial::ETAT_R === $normalized) {
            throw new ConflictException('Pour réformer un bien, utilisez l’action Réformer.');
        }

        return $normalized;
    }

    private function parseDate(?string $value): ?\DateTimeImmutable
    {
        if (null === $value || '' === trim($value)) {
            return null;
        }

        try {
            return new \DateTimeImmutable($value);
        } catch (\Exception) {
            throw new ConflictException('Date d’acquisition invalide.');
        }
    }

    private function trimOrNull(?string $value): ?string
    {
        if (null === $value) {
            return null;
        }
        $trimmed = trim($value);

        return '' === $trimmed ? null : $trimmed;
    }

    private function record(
        BienPatrimonial $bien,
        string $type,
        ?string $etatAvant = null,
        ?string $etatApres = null,
        ?Service $serviceAvant = null,
        ?Service $serviceApres = null,
        ?LocalIntendance $localAvant = null,
        ?LocalIntendance $localApres = null,
        ?string $codeAvant = null,
        ?string $codeApres = null,
        ?string $motif = null,
        bool $flush = true,
    ): void {
        $ligne = (new HistoriqueBien())
            ->setBien($bien)
            ->setType($type)
            ->setEtatAvant($etatAvant)
            ->setEtatApres($etatApres)
            ->setServiceAvant($serviceAvant)
            ->setServiceApres($serviceApres)
            ->setLocalAvant($localAvant)
            ->setLocalApres($localApres)
            ->setCodeAvant($codeAvant)
            ->setCodeApres($codeApres)
            ->setMotif($motif)
            ->setCreatedBy($this->currentPersonnel())
            ->setCreatedAt(new \DateTimeImmutable());

        $this->entityManager->persist($ligne);
        if ($flush) {
            $this->entityManager->flush();
        }
    }

    private function currentPersonnel(): ?Personnel
    {
        $user = $this->security->getUser();

        return $user instanceof Personnel ? $user : null;
    }

    private function assertValid(object $input): void
    {
        $errors = $this->validator->validate($input);
        if (count($errors) > 0) {
            throw new ValidationFailedException($input, $errors);
        }
    }
}
