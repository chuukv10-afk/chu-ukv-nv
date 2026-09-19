<?php

namespace App\Service\Clinique;

use App\DTO\Clinique\AptitudeListQuery;
use App\DTO\Clinique\AptitudeStatsQuery;
use App\DTO\Clinique\UpsertAptitudeInput;
use App\DTO\Common\PaginatedResult;
use App\Entity\CertificatAptitude;
use App\Entity\Filiere;
use App\Entity\Personnel;
use App\Entity\Service;
use App\Exception\ConflictException;
use App\Exception\NotFoundException;
use App\Repository\CertificatAptitudeRepository;
use App\Repository\FiliereRepository;
use App\Repository\ServiceRepository;
use App\Security\Permission\CliniquePermissions;
use App\Service\Patient\PatientService;
use App\Service\Referentiel\FiliereService;
use App\Service\Referentiel\OrganisationPartenaireService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\Validator\Exception\ValidationFailedException;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final class AptitudeService
{
    private const TIMEZONE = 'Africa/Kinshasa';

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly CertificatAptitudeRepository $repository,
        private readonly ServiceRepository $serviceRepository,
        private readonly FiliereRepository $filiereRepository,
        private readonly FiliereService $filiereService,
        private readonly OrganisationPartenaireService $organisationPartenaireService,
        private readonly PatientService $patientService,
        private readonly ValidatorInterface $validator,
        private readonly Security $security,
    ) {
    }

    public function paginate(AptitudeListQuery $query): PaginatedResult
    {
        $this->assertValid($query);
        $result = $this->repository->paginate(
            $query->page,
            $query->limit,
            $query->search,
            $query->annee,
            $query->statut,
            $query->verdict,
            $query->motif,
            $query->serviceId,
            $query->filiereId,
            $query->sansFiliere,
            $query->imprime,
            $query->numero,
        );

        return new PaginatedResult(
            array_map([$this, 'serializeSummary'], $result['items']),
            $query->page,
            $query->limit,
            $result['total'],
        );
    }

    /**
     * @return list<list<string|null>>
     */
    public function buildExportRows(AptitudeListQuery $query): array
    {
        $this->assertValid($query);
        $items = $this->repository->findForExport(
            $query->search,
            $query->annee,
            $query->statut,
            $query->verdict,
            $query->motif,
            $query->serviceId,
            $query->filiereId,
            $query->sansFiliere,
            $query->imprime,
            $query->numero,
        );

        $rows = [];
        $index = 1;
        foreach ($items as $item) {
            $rows[] = [
                (string) $index,
                $item->getNumero() ?? '—',
                $item->getFullName(),
                $item->getSexe(),
                $item->getService()?->getLibelle(),
                $this->motifLabel($item),
                $this->filiereLabel($item),
                $item->getVerdict(),
                $item->getStatut(),
                $item->getSigneAt()?->format('d/m/Y'),
                $item->getValideJusqua()?->format('d/m/Y'),
                $item->isImprime() ? 'Oui' : 'Non',
            ];
            ++$index;
        }

        return $rows;
    }

    /**
     * @return list<string>
     */
    public function exportHeaders(): array
    {
        return ['N°', 'Numéro', 'Candidat', 'Sexe', 'Service', 'Motif', 'Filière', 'Verdict', 'Statut', 'Signé le', 'Valable jusqu\'au', 'Imprimé'];
    }

    /** @return list<array{id: int, code: string, libelle: string}> */
    public function listServices(): array
    {
        $services = $this->serviceRepository->findBy([], ['libelle' => 'ASC']);

        return array_map(static fn (Service $service): array => [
            'id' => $service->getId(),
            'code' => $service->getCode(),
            'libelle' => $service->getLibelle(),
        ], $services);
    }

    /** @return list<array{id: int, code: string, libelle: string, organisationId: int|null}> */
    public function listFilieres(?int $organisationId = null): array
    {
        return $this->filiereService->listLookup($organisationId);
    }

    /** @return list<array<string, mixed>> */
    public function listOrganisations(): array
    {
        return $this->organisationPartenaireService->listLookup();
    }

    /** @return array<string, mixed> */
    public function meta(): array
    {
        return [
            'statuts' => CertificatAptitude::getStatuts(),
            'motifs' => CertificatAptitude::getMotifs(),
            'verdicts' => CertificatAptitude::getVerdicts(),
        ];
    }

    public function getById(int $id): CertificatAptitude
    {
        $certificat = $this->repository->find($id);
        if (null === $certificat) {
            throw new NotFoundException('Certificat d\'aptitude non trouvé.');
        }

        return $certificat;
    }

    /**
     * @param list<int> $ids
     *
     * @return list<CertificatAptitude>
     */
    public function getPrintableByIds(array $ids): array
    {
        if (count($ids) > 80) {
            throw new ConflictException('Maximum 80 certificats à imprimer à la fois.');
        }
        $items = $this->repository->findOrderedByIds($ids);
        if ([] === $items) {
            throw new NotFoundException('Aucun certificat sélectionné.');
        }
        foreach ($items as $item) {
            if ($item->isBrouillon()) {
                throw new ConflictException(sprintf(
                    'Le certificat de %s est encore un brouillon : signez-le avant l\'impression.',
                    $item->getFullName(),
                ));
            }
        }

        return $items;
    }

    /**
     * @param list<int> $ids
     *
     * @return array{updated: int, ids: list<int>}
     */
    public function markPrinted(array $ids): array
    {
        $items = $this->getPrintableByIds($ids);
        $personnel = $this->currentPersonnel();
        $now = new \DateTimeImmutable('now', new \DateTimeZone(self::TIMEZONE));
        $updated = 0;
        foreach ($items as $item) {
            if ($item->isImprime()) {
                continue;
            }
            $item
                ->setImprime(true)
                ->setImprimeAt($now)
                ->setImprimePar($personnel);
            ++$updated;
        }
        $this->entityManager->flush();

        return [
            'updated' => $updated,
            'ids' => array_map(static fn (CertificatAptitude $item): int => (int) $item->getId(), $items),
        ];
    }

    public function create(UpsertAptitudeInput $input): CertificatAptitude
    {
        $this->assertValid($input);
        $certificat = new CertificatAptitude();
        $certificat->setAnnee($this->currentYear());
        $this->hydrate($certificat, $input, true);
        $this->entityManager->persist($certificat);
        $this->entityManager->flush();

        return $certificat;
    }

    public function update(int $id, UpsertAptitudeInput $input): CertificatAptitude
    {
        if (!$this->canUpdateAnySection()) {
            throw new AccessDeniedHttpException('Vous n\'avez pas le droit de modifier une section de ce certificat.');
        }
        $this->assertValid($input);
        $certificat = $this->getById($id);
        $this->assertBrouillon($certificat);
        $this->hydrate($certificat, $input, false);
        $this->entityManager->flush();

        return $certificat;
    }

    public function delete(int $id): void
    {
        $certificat = $this->getById($id);
        $this->assertCanDelete($certificat);
        $this->entityManager->remove($certificat);
        $this->entityManager->flush();
    }

    /**
     * @param list<int> $ids
     *
     * @return array{deleted: int, ids: list<int>}
     */
    public function deleteMany(array $ids): array
    {
        $normalized = [];
        foreach ($ids as $id) {
            $value = (int) $id;
            if ($value > 0) {
                $normalized[$value] = $value;
            }
        }
        $normalized = array_values($normalized);
        if ([] === $normalized) {
            throw new ConflictException('Sélectionnez au moins un certificat.');
        }
        if (count($normalized) > 80) {
            throw new ConflictException('Maximum 80 certificats à supprimer à la fois.');
        }

        $items = $this->repository->findOrderedByIds($normalized);
        if (count($items) !== count($normalized)) {
            throw new NotFoundException('Un ou plusieurs certificats sélectionnés sont introuvables.');
        }

        foreach ($items as $item) {
            $this->assertCanDelete($item);
        }
        foreach ($items as $item) {
            $this->entityManager->remove($item);
        }
        $this->entityManager->flush();

        return [
            'deleted' => count($items),
            'ids' => array_map(static fn (CertificatAptitude $item): int => (int) $item->getId(), $items),
        ];
    }

    public function signer(int $id): CertificatAptitude
    {
        $certificat = $this->getById($id);
        $this->assertBrouillon($certificat);
        $this->assertSignable($certificat);

        $personnel = $this->currentPersonnel();
        if (!$personnel instanceof Personnel) {
            throw new ConflictException('Impossible de déterminer le médecin signataire.');
        }

        $now = new \DateTimeImmutable('now', new \DateTimeZone(self::TIMEZONE));
        $year = (int) $now->format('Y');
        $this->entityManager->beginTransaction();
        try {
            $sequence = $this->repository->nextSequenceForYear($year);

            $certificat
                ->setStatut(CertificatAptitude::STATUT_SIGNE)
                ->setAnnee($year)
                ->setNumero(sprintf('%04d / CHU-UKV / CAP / %d', $sequence, $year))
                ->setSigneAt($now)
                ->setValideJusqua($now->modify('+3 months'))
                ->setSignePar($personnel);

            $this->entityManager->flush();
            $this->entityManager->commit();
        } catch (\Throwable $exception) {
            $this->entityManager->rollback();
            throw $exception;
        }

        return $certificat;
    }

    public function annuler(int $id): CertificatAptitude
    {
        $certificat = $this->getById($id);
        if (! $certificat->isSigne()) {
            throw new ConflictException('Seul un certificat signé peut être annulé.');
        }

        $certificat
            ->setStatut(CertificatAptitude::STATUT_ANNULE)
            ->setAnnuleAt(new \DateTimeImmutable('now', new \DateTimeZone(self::TIMEZONE)));
        $this->entityManager->flush();

        return $certificat;
    }

    /** @return array<string, mixed> */
    public function serializeSummary(CertificatAptitude $certificat): array
    {
        $now = new \DateTimeImmutable('now', new \DateTimeZone(self::TIMEZONE));
        $expired = $certificat->isSigne()
            && null !== $certificat->getValideJusqua()
            && $certificat->getValideJusqua() < $now;

        return [
            'id' => $certificat->getId(),
            'numero' => $certificat->getNumero(),
            'annee' => $certificat->getAnnee(),
            'statut' => $certificat->getStatut(),
            'nom' => $certificat->getNom(),
            'postNom' => $certificat->getPostNom(),
            'prenom' => $certificat->getPrenom(),
            'fullName' => $certificat->getFullName(),
            'sexe' => $certificat->getSexe(),
            'motif' => $certificat->getMotif(),
            'motifLabel' => $this->motifLabel($certificat),
            'filiere' => $this->serializeFiliere($certificat->getFiliere()),
            'verdict' => $this->canViewSection(CliniquePermissions::APTITUDE_VERDICT_READ, CliniquePermissions::APTITUDE_VERDICT_UPDATE)
                ? $certificat->getVerdict() : null,
            'verdictPropose' => $this->canViewSection(CliniquePermissions::APTITUDE_VERDICT_READ, CliniquePermissions::APTITUDE_VERDICT_UPDATE)
                ? $certificat->getVerdictPropose() : null,
            'service' => $this->serializeService($certificat->getService()),
            'patientId' => $certificat->getPatient()?->getId()?->toRfc4122(),
            'signeAt' => $certificat->getSigneAt()?->format(\DateTimeInterface::ATOM),
            'valideJusqua' => $certificat->getValideJusqua()?->format(\DateTimeInterface::ATOM),
            'imprime' => $certificat->isImprime(),
            'imprimeAt' => $certificat->getImprimeAt()?->format(\DateTimeInterface::ATOM),
            'expired' => $expired,
            'createdAt' => $certificat->getCreatedAt()?->format(\DateTimeInterface::ATOM),
        ];
    }

    /** @return array<string, mixed> */
    public function serializeDetail(CertificatAptitude $certificat): array
    {
        $signePar = $certificat->getSignePar();

        return [
            ...$this->serializeSummary($certificat),
            'etatCivil' => $this->canViewSection(CliniquePermissions::APTITUDE_IDENTITE_READ, CliniquePermissions::APTITUDE_IDENTITE_UPDATE)
                ? $certificat->getEtatCivil() : null,
            'dateNaissance' => $this->canViewSection(CliniquePermissions::APTITUDE_IDENTITE_READ, CliniquePermissions::APTITUDE_IDENTITE_UPDATE)
                ? $certificat->getDateNaissance()?->format('Y-m-d') : null,
            'lieuNaissance' => $this->canViewSection(CliniquePermissions::APTITUDE_IDENTITE_READ, CliniquePermissions::APTITUDE_IDENTITE_UPDATE)
                ? $certificat->getLieuNaissance() : null,
            'adresse' => $this->canViewSection(CliniquePermissions::APTITUDE_IDENTITE_READ, CliniquePermissions::APTITUDE_IDENTITE_UPDATE)
                ? $certificat->getAdresse() : null,
            'motifAutre' => $this->canViewSection(CliniquePermissions::APTITUDE_IDENTITE_READ, CliniquePermissions::APTITUDE_IDENTITE_UPDATE)
                ? $certificat->getMotifAutre() : null,
            'poidsKg' => $this->canViewSection(CliniquePermissions::APTITUDE_IMC_READ, CliniquePermissions::APTITUDE_IMC_UPDATE)
                ? $this->asFloat($certificat->getPoidsKg()) : null,
            'tailleM' => $this->canViewSection(CliniquePermissions::APTITUDE_IMC_READ, CliniquePermissions::APTITUDE_IMC_UPDATE)
                ? $this->asFloat($certificat->getTailleM()) : null,
            'perimetreThoraciqueCm' => $this->canViewSection(CliniquePermissions::APTITUDE_PIGNET_READ, CliniquePermissions::APTITUDE_PIGNET_UPDATE)
                ? $this->asFloat($certificat->getPerimetreThoraciqueCm()) : null,
            'p1' => $this->canViewSection(CliniquePermissions::APTITUDE_RUFFIER_READ, CliniquePermissions::APTITUDE_RUFFIER_UPDATE)
                ? $certificat->getP1() : null,
            'p2' => $this->canViewSection(CliniquePermissions::APTITUDE_RUFFIER_READ, CliniquePermissions::APTITUDE_RUFFIER_UPDATE)
                ? $certificat->getP2() : null,
            'p3' => $this->canViewSection(CliniquePermissions::APTITUDE_RUFFIER_READ, CliniquePermissions::APTITUDE_RUFFIER_UPDATE)
                ? $certificat->getP3() : null,
            'imc' => $this->canViewSection(CliniquePermissions::APTITUDE_IMC_READ, CliniquePermissions::APTITUDE_IMC_UPDATE)
                ? $this->asFloat($certificat->getImc()) : null,
            'imcClasse' => $this->canViewSection(CliniquePermissions::APTITUDE_IMC_READ, CliniquePermissions::APTITUDE_IMC_UPDATE)
                ? $certificat->getImcClasse() : null,
            'imcClasseProposee' => $this->canViewSection(CliniquePermissions::APTITUDE_IMC_READ, CliniquePermissions::APTITUDE_IMC_UPDATE)
                ? AptitudeCalculator::imcClasse($this->asFloat($certificat->getImc())) : null,
            'pignet' => $this->canViewSection(CliniquePermissions::APTITUDE_PIGNET_READ, CliniquePermissions::APTITUDE_PIGNET_UPDATE)
                ? $this->asFloat($certificat->getPignet()) : null,
            'pignetRobustesse' => $this->canViewSection(CliniquePermissions::APTITUDE_PIGNET_READ, CliniquePermissions::APTITUDE_PIGNET_UPDATE)
                ? $certificat->getPignetRobustesse() : null,
            'ruffier' => $this->canViewSection(CliniquePermissions::APTITUDE_RUFFIER_READ, CliniquePermissions::APTITUDE_RUFFIER_UPDATE)
                ? $this->asFloat($certificat->getRuffier()) : null,
            'dickson' => $this->canViewSection(CliniquePermissions::APTITUDE_RUFFIER_READ, CliniquePermissions::APTITUDE_RUFFIER_UPDATE)
                ? $this->asFloat($certificat->getDickson()) : null,
            'ruffierClasse' => $this->canViewSection(CliniquePermissions::APTITUDE_RUFFIER_READ, CliniquePermissions::APTITUDE_RUFFIER_UPDATE)
                ? $certificat->getRuffierClasse() : null,
            'dicksonClasse' => $this->canViewSection(CliniquePermissions::APTITUDE_RUFFIER_READ, CliniquePermissions::APTITUDE_RUFFIER_UPDATE)
                ? $certificat->getDicksonClasse() : null,
            'signePar' => null !== $signePar ? [
                'id' => (string) $signePar->getId(),
                'fullName' => trim(sprintf(
                    '%s %s %s',
                    $signePar->getPrenom() ?? '',
                    $signePar->getNom() ?? '',
                    $signePar->getPostNom() ?? '',
                )),
            ] : null,
            'annuleAt' => $certificat->getAnnuleAt()?->format(\DateTimeInterface::ATOM),
            'updatedAt' => $certificat->getUpdatedAt()?->format(\DateTimeInterface::ATOM),
        ];
    }

    private function hydrate(CertificatAptitude $certificat, UpsertAptitudeInput $input, bool $full): void
    {
        $applyIdentite = $full || $this->canUpdateSection(CliniquePermissions::APTITUDE_IDENTITE_UPDATE);
        $applyImc = $full || $this->canUpdateSection(CliniquePermissions::APTITUDE_IMC_UPDATE);
        $applyPignet = $full || $this->canUpdateSection(CliniquePermissions::APTITUDE_PIGNET_UPDATE);
        $applyRuffier = $full || $this->canUpdateSection(CliniquePermissions::APTITUDE_RUFFIER_UPDATE);
        $applyVerdict = $full || $this->canUpdateSection(CliniquePermissions::APTITUDE_VERDICT_UPDATE);

        if ($applyIdentite) {
            $service = $this->serviceRepository->find($input->serviceId);
            if (!$service instanceof Service) {
                throw new NotFoundException('Service non trouvé.');
            }

            if (CertificatAptitude::MOTIF_AUTRE === $input->motif && (null === $input->motifAutre || '' === trim($input->motifAutre))) {
                throw new ConflictException('Précisez le motif lorsque « Autre » est sélectionné.');
            }

            $patient = null;
            if (null !== $input->patientId) {
                $patient = $this->patientService->getById($input->patientId);
            }

            $certificat
                ->setService($service)
                ->setPatient($patient)
                ->setNom(mb_strtoupper(trim($input->nom)))
                ->setPostNom(mb_strtoupper(trim($input->postNom)))
                ->setPrenom($this->blankToNull($input->prenom))
                ->setSexe(strtoupper(trim($input->sexe)))
                ->setEtatCivil($this->blankToNull($input->etatCivil))
                ->setDateNaissance($this->parseDate($input->dateNaissance))
                ->setLieuNaissance($this->blankToNull($input->lieuNaissance))
                ->setAdresse($this->blankToNull($input->adresse))
                ->setMotif($input->motif)
                ->setMotifAutre(CertificatAptitude::MOTIF_AUTRE === $input->motif ? $this->blankToNull($input->motifAutre) : null)
                ->setFiliere($this->resolveFiliere($input));
        }

        $poids = $applyImc ? $this->toFloat($input->poidsKg) : $this->asFloat($certificat->getPoidsKg());
        $taille = AptitudeCalculator::tailleMetres(
            $applyImc ? $this->toFloat($input->tailleM) : $this->asFloat($certificat->getTailleM()),
        );
        $perimetre = $applyPignet
            ? $this->toFloat($input->perimetreThoraciqueCm)
            : $this->asFloat($certificat->getPerimetreThoraciqueCm());
        $p1 = $applyRuffier ? $this->toInt($input->p1) : $certificat->getP1();
        $p2 = $applyRuffier ? $this->toInt($input->p2) : $certificat->getP2();
        $p3 = $applyRuffier ? $this->toInt($input->p3) : $certificat->getP3();
        $computed = AptitudeCalculator::compute($poids, $taille, $perimetre, $p1, $p2, $p3);

        if ($applyImc) {
            $imcClasse = $this->normalizeImcClasse($input->imcClasse);
            if (null === $imcClasse) {
                $imcClasse = $computed['imcClasse'];
            }
            $certificat
                ->setPoidsKg($this->toDecimal($poids))
                ->setTailleM($this->toDecimal($taille))
                ->setImc($this->toDecimal($computed['imc']))
                ->setImcClasse($imcClasse);
        }

        if ($applyPignet) {
            $certificat
                ->setPerimetreThoraciqueCm($this->toDecimal($perimetre))
                ->setPignet($this->toDecimal($computed['pignet']))
                ->setPignetRobustesse($computed['pignetRobustesse']);
        }

        if ($applyRuffier) {
            $certificat
                ->setP1($p1)
                ->setP2($p2)
                ->setP3($p3)
                ->setRuffier($this->toDecimal($computed['ruffier']))
                ->setDickson($this->toDecimal($computed['dickson']))
                ->setRuffierClasse($computed['ruffierClasse'])
                ->setDicksonClasse($computed['dicksonClasse']);
        }

        if ($applyImc || $applyPignet || $applyRuffier) {
            $certificat->setVerdictPropose($computed['verdictPropose']);
        }

        if ($applyVerdict) {
            $verdict = $this->normalizeVerdict($input->verdict);
            if (null === $verdict) {
                $verdict = $computed['verdictPropose'];
            }
            $certificat->setVerdict($verdict);
        }
    }

    private function canUpdateAnySection(): bool
    {
        return $this->canUpdateSection(CliniquePermissions::APTITUDE_IDENTITE_UPDATE)
            || $this->canUpdateSection(CliniquePermissions::APTITUDE_IMC_UPDATE)
            || $this->canUpdateSection(CliniquePermissions::APTITUDE_PIGNET_UPDATE)
            || $this->canUpdateSection(CliniquePermissions::APTITUDE_RUFFIER_UPDATE)
            || $this->canUpdateSection(CliniquePermissions::APTITUDE_VERDICT_UPDATE);
    }

    private function canUpdateSection(string $permission): bool
    {
        return $this->security->isGranted($permission);
    }

    private function canViewSection(string $readPermission, string $updatePermission): bool
    {
        return $this->security->isGranted($readPermission)
            || $this->security->isGranted($updatePermission);
    }

    private function assertSignable(CertificatAptitude $certificat): void
    {
        $missing = [];
        if ('' === trim($certificat->getNom()) || '' === trim($certificat->getPostNom())) {
            $missing[] = 'identité';
        }
        if ('' === trim((string) $certificat->getSexe())) {
            $missing[] = 'sexe';
        }
        if (null === $certificat->getService()) {
            $missing[] = 'service';
        }
        if (CertificatAptitude::MOTIF_ADMISSION_UKV === $certificat->getMotif() && !$certificat->getFiliere() instanceof Filiere) {
            $missing[] = 'filière';
        }

        if ([] !== $missing) {
            throw new ConflictException('Complétez le certificat avant signature : ' . implode(', ', $missing) . '.');
        }
    }

    private function assertBrouillon(CertificatAptitude $certificat): void
    {
        if (!$certificat->isBrouillon()) {
            throw new ConflictException('Seul un brouillon peut être modifié.');
        }
    }

    private function assertCanDelete(CertificatAptitude $certificat): void
    {
        if ($this->security->isGranted(CliniquePermissions::APTITUDE_DELETE_DEFINITIF)) {
            return;
        }
        if ($certificat->isBrouillon() && $this->security->isGranted(CliniquePermissions::APTITUDE_DELETE)) {
            return;
        }

        throw new AccessDeniedHttpException(
            $certificat->isBrouillon()
                ? 'Permission requise pour supprimer un brouillon.'
                : 'Permission requise pour supprimer définitivement un certificat signé ou annulé.'
        );
    }

    /** @return array<string, mixed> */
    public function stats(AptitudeStatsQuery $query): array
    {
        $this->assertValid($query);
        $rows = $this->repository->countByFiliere($query->annee, $query->statut, $query->verdict, $query->motif);
        $byId = [];
        $sansFiliere = $this->emptyStatRow(null, null, 'Non renseignée');

        foreach ($rows as $row) {
            $id = null !== $row['filiereId'] && '' !== (string) $row['filiereId']
                ? (int) $row['filiereId']
                : null;
            $stat = [
                'filiereId' => $id,
                'code' => $row['filiereCode'] ?? null,
                'libelle' => $row['filiereLibelle'] ?? 'Non renseignée',
                'total' => (int) $row['total'],
                'apte' => (int) $row['apte'],
                'inapte' => (int) $row['inapte'],
                'brouillon' => (int) $row['brouillon'],
                'signe' => (int) $row['signe'],
                'annule' => (int) $row['annule'],
            ];
            if (null === $id) {
                $sansFiliere = $stat;
            } else {
                $byId[$id] = $stat;
            }
        }

        $expandAll = null === $query->motif || CertificatAptitude::MOTIF_ADMISSION_UKV === $query->motif;
        $items = [];
        if ($expandAll) {
            foreach ($this->filiereRepository->findAllOrdered() as $filiere) {
                $id = (int) $filiere->getId();
                $items[] = $byId[$id] ?? $this->emptyStatRow($id, $filiere->getCode(), $filiere->getLibelle());
            }
        } else {
            $items = array_values($byId);
        }
        if ($sansFiliere['total'] > 0) {
            $items[] = $sansFiliere;
        }

        $totals = ['total' => 0, 'apte' => 0, 'inapte' => 0, 'brouillon' => 0, 'signe' => 0, 'annule' => 0];
        foreach ($items as $item) {
            foreach (array_keys($totals) as $key) {
                $totals[$key] += $item[$key];
            }
        }

        return [
            'filters' => [
                'annee' => $query->annee,
                'statut' => $query->statut,
                'verdict' => $query->verdict,
                'motif' => $query->motif,
            ],
            'totals' => $totals,
            'byFiliere' => $items,
        ];
    }

    /**
     * @return list<list<string|null>>
     */
    public function buildStatsExportRows(AptitudeStatsQuery $query): array
    {
        $stats = $this->stats($query);
        $rows = [];
        $index = 1;
        foreach ($stats['byFiliere'] as $item) {
            $rows[] = [
                (string) $index,
                $item['code'] ?? '—',
                $item['libelle'],
                (string) $item['total'],
                (string) $item['apte'],
                (string) $item['inapte'],
                (string) $item['brouillon'],
                (string) $item['signe'],
                (string) $item['annule'],
            ];
            ++$index;
        }
        $totals = $stats['totals'];
        $rows[] = [
            '',
            '',
            'Total',
            (string) $totals['total'],
            (string) $totals['apte'],
            (string) $totals['inapte'],
            (string) $totals['brouillon'],
            (string) $totals['signe'],
            (string) $totals['annule'],
        ];

        return $rows;
    }

    /** @return list<string> */
    public function statsExportHeaders(): array
    {
        return ['N°', 'Code', 'Filière', 'Total', 'APTE', 'INAPTE', 'Brouillon', 'Signé', 'Annulé'];
    }

    public function statsExportTitle(AptitudeStatsQuery $query): string
    {
        $parts = ['Statistiques aptitude physique'];
        if (CertificatAptitude::MOTIF_ADMISSION_UKV === $query->motif) {
            $parts[] = 'Admission UKV';
        }
        if (null !== $query->annee) {
            $parts[] = (string) $query->annee;
        }

        return implode(' — ', $parts);
    }

    /** @return array<string, mixed> */
    public function serializePublicVerification(CertificatAptitude $certificat): array
    {
        $now = new \DateTimeImmutable('now', new \DateTimeZone(self::TIMEZONE));
        $expired = $certificat->isSigne()
            && null !== $certificat->getValideJusqua()
            && $certificat->getValideJusqua() < $now;
        $doctor = $certificat->getSignePar();

        return [
            'numero' => $certificat->getNumero(),
            'statut' => $certificat->getStatut(),
            'authentique' => CertificatAptitude::STATUT_SIGNE === $certificat->getStatut() && !$expired,
            'expired' => $expired,
            'fullName' => $certificat->getFullName(),
            'sexe' => $certificat->getSexe(),
            'motif' => $certificat->getMotif(),
            'motifLabel' => $this->motifLabel($certificat),
            'filiere' => $this->serializeFiliere($certificat->getFiliere()),
            'verdict' => $certificat->getVerdict(),
            'signeAt' => $certificat->getSigneAt()?->format(\DateTimeInterface::ATOM),
            'valideJusqua' => $certificat->getValideJusqua()?->format(\DateTimeInterface::ATOM),
            'medecinExaminateur' => null !== $doctor ? trim(sprintf(
                '%s %s %s',
                $doctor->getPrenom() ?? '',
                $doctor->getNom() ?? '',
                $doctor->getPostNom() ?? '',
            )) : null,
            'contactEmail' => 'doc-verification@chu-ukv.cd',
        ];
    }

    public function verifyOfficialByNumero(string $numero): CertificatAptitude
    {
        $certificat = $this->repository->findOfficialByNumero($numero);
        if (!$certificat instanceof CertificatAptitude) {
            throw new NotFoundException('Aucun certificat officiel ne correspond à ce numéro.');
        }

        return $certificat;
    }

    private function motifLabel(CertificatAptitude $certificat): string
    {
        return match ($certificat->getMotif()) {
            CertificatAptitude::MOTIF_ADMISSION_UKV => 'Admission Universitaire UKV',
            CertificatAptitude::MOTIF_EMPLOI => 'Emploi',
            CertificatAptitude::MOTIF_AUTRE => $certificat->getMotifAutre() ?: 'Autre',
            default => $certificat->getMotif(),
        };
    }

    private function filiereLabel(CertificatAptitude $certificat): string
    {
        $filiere = $certificat->getFiliere();
        if (!$filiere instanceof Filiere) {
            return CertificatAptitude::MOTIF_ADMISSION_UKV === $certificat->getMotif() ? 'Non renseignée' : '—';
        }

        return trim(sprintf('%s — %s', $filiere->getCode() ?? '', $filiere->getLibelle() ?? ''));
    }

    private function resolveFiliere(UpsertAptitudeInput $input): ?Filiere
    {
        if (CertificatAptitude::MOTIF_ADMISSION_UKV !== $input->motif) {
            return null;
        }
        if (null === $input->filiereId) {
            return null;
        }

        $filiere = $this->filiereRepository->find($input->filiereId);
        if (!$filiere instanceof Filiere) {
            throw new NotFoundException('Filière non trouvée.');
        }

        return $filiere;
    }

    /** @return array{id: int, code: string, libelle: string}|null */
    private function serializeFiliere(?Filiere $filiere): ?array
    {
        if (!$filiere instanceof Filiere) {
            return null;
        }

        return [
            'id' => (int) $filiere->getId(),
            'code' => (string) $filiere->getCode(),
            'libelle' => (string) $filiere->getLibelle(),
        ];
    }

    /**
     * @return array{filiereId: int|null, code: string|null, libelle: string, total: int, apte: int, inapte: int, brouillon: int, signe: int, annule: int}
     */
    private function emptyStatRow(?int $id, ?string $code, string $libelle): array
    {
        return [
            'filiereId' => $id,
            'code' => $code,
            'libelle' => $libelle,
            'total' => 0,
            'apte' => 0,
            'inapte' => 0,
            'brouillon' => 0,
            'signe' => 0,
            'annule' => 0,
        ];
    }

    /** @return array{id: int, code: string, libelle: string}|null */
    private function serializeService(?Service $service): ?array
    {
        if (null === $service) {
            return null;
        }

        return [
            'id' => $service->getId(),
            'code' => (string) $service->getCode(),
            'libelle' => (string) $service->getLibelle(),
        ];
    }

    private function currentPersonnel(): ?Personnel
    {
        $user = $this->security->getUser();

        return $user instanceof Personnel ? $user : null;
    }

    private function currentYear(): int
    {
        return (int) (new \DateTimeImmutable('now', new \DateTimeZone(self::TIMEZONE)))->format('Y');
    }

    private function parseDate(?string $value): ?\DateTimeImmutable
    {
        if (null === $value || '' === trim($value)) {
            return null;
        }

        $date = \DateTimeImmutable::createFromFormat('Y-m-d', trim($value));
        if (false === $date) {
            throw new ConflictException('Date de naissance invalide.');
        }

        return $date->setTime(0, 0);
    }

    private function normalizeVerdict(?string $verdict): ?string
    {
        if (null === $verdict || '' === trim($verdict)) {
            return null;
        }

        $normalized = strtoupper(trim($verdict));
        if (!in_array($normalized, CertificatAptitude::getVerdicts(), true)) {
            throw new ConflictException('Verdict invalide.');
        }

        return $normalized;
    }

    private function normalizeImcClasse(?string $classe): ?string
    {
        if (null === $classe || '' === trim($classe)) {
            return null;
        }

        $normalized = strtoupper(trim($classe));
        if (!in_array($normalized, CertificatAptitude::getImcClasses(), true)) {
            throw new ConflictException('Interprétation OMS invalide.');
        }

        return $normalized;
    }

    private function toFloat(mixed $value): ?float
    {
        if (null === $value || '' === $value) {
            return null;
        }
        if (!is_numeric($value)) {
            throw new ConflictException('Valeur numérique invalide.');
        }

        return (float) $value;
    }

    private function toInt(mixed $value): ?int
    {
        if (null === $value || '' === $value) {
            return null;
        }
        if (!is_numeric($value)) {
            throw new ConflictException('Valeur numérique invalide.');
        }

        return (int) $value;
    }

    private function toDecimal(?float $value): ?string
    {
        return null === $value ? null : number_format($value, 2, '.', '');
    }

    private function asFloat(?string $value): ?float
    {
        return null === $value || '' === $value ? null : (float) $value;
    }

    private function blankToNull(?string $value): ?string
    {
        if (null === $value) {
            return null;
        }
        $trimmed = trim($value);

        return '' === $trimmed ? null : $trimmed;
    }

    private function assertValid(object $input): void
    {
        $errors = $this->validator->validate($input);
        if (count($errors) > 0) {
            throw new ValidationFailedException($input, $errors);
        }
    }
}
