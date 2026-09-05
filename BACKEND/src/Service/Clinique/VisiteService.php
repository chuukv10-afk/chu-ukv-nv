<?php

namespace App\Service\Clinique;

use App\DTO\Clinique\CreateVisiteInput;
use App\DTO\Clinique\TriageSigneVitalInput;
use App\DTO\Clinique\UpdateVisiteInput;
use App\DTO\Clinique\VisiteListQuery;
use App\DTO\Common\PaginatedResult;
use App\Entity\ActeFinancier;
use App\Entity\ActeFinancierVisite;
use App\Entity\Consultation;
use App\Entity\Dpi;
use App\Entity\Lit;
use App\Entity\Personnel;
use App\Entity\Service;
use App\Entity\SigneVital;
use App\Entity\Triage;
use App\Entity\TriageMesure;
use App\Entity\Visite;
use App\Exception\ConflictException;
use App\Exception\NotFoundException;
use App\Repository\ActeFinancierRepository;
use App\Repository\DpiRepository;
use App\Repository\LitRepository;
use App\Repository\ServiceRepository;
use App\Repository\SigneVitalRepository;
use App\Repository\VisiteRepository;
use App\Security\Permission\CliniquePermissions;
use App\Security\Permission\ReferentielPermissions;
use App\Security\PermissionChecker;
use App\Security\PersonnelAccessScope;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Validator\Exception\ValidationFailedException;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final class VisiteService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly VisiteRepository $visiteRepository,
        private readonly DpiRepository $dpiRepository,
        private readonly ServiceRepository $serviceRepository,
        private readonly LitRepository $litRepository,
        private readonly SigneVitalRepository $signeVitalRepository,
        private readonly ActeFinancierRepository $acteFinancierRepository,
        private readonly ConsultationService $consultationService,
        private readonly ValidatorInterface $validator,
        private readonly PermissionChecker $permissionChecker,
        private readonly Security $security,
    ) {
    }

    public function paginate(VisiteListQuery $query): PaginatedResult
    {
        $errors = $this->validator->validate($query);
        if (count($errors) > 0) {
            throw new ValidationFailedException($query, $errors);
        }

        $pendingHospitalization = $query->wantsPendingHospitalization();
        $result = $this->visiteRepository->paginate(
            $query->page,
            $query->limit,
            $query->search,
            $query->statut,
            $query->serviceId,
            $query->dpiId,
            $query->patientId,
            $this->resolveAccessScope(),
            $this->parseDateStart($query->enterFrom),
            $this->parseDateEndExclusive($query->enterTo),
            $pendingHospitalization,
        );

        $pendingIds = $this->visiteRepository->findPendingHospitalizationIds(
            array_map(static fn (Visite $visite): int => (int) $visite->getId(), $result['items']),
        );

        return new PaginatedResult(
            array_map(
                fn (Visite $visite): array => $this->serializeSummary(
                    $visite,
                    in_array((int) $visite->getId(), $pendingIds, true),
                ),
                $result['items'],
            ),
            $query->page,
            $query->limit,
            $result['total'],
        );
    }

    /**
     * @return list<list<string|null>>
     */
    public function buildExportRows(VisiteListQuery $query): array
    {
        $errors = $this->validator->validate($query);
        if (count($errors) > 0) {
            throw new ValidationFailedException($query, $errors);
        }

        $items = $this->visiteRepository->findForExport(
            $query->search,
            $query->statut,
            $query->serviceId,
            $query->dpiId,
            $query->patientId,
            $this->resolveAccessScope(),
            $this->parseDateStart($query->enterFrom),
            $this->parseDateEndExclusive($query->enterTo),
            $query->wantsPendingHospitalization(),
        );

        return array_map(
            fn (Visite $visite): array => $this->buildExportRow($visite),
            $items,
        );
    }

    /** @return list<string|null> */
    public function buildExportRow(Visite $visite): array
    {
        $patient = $visite->getDpi()?->getPatient();
        $lit = $visite->getLit();

        return [
            (string) $visite->getId(),
            $visite->getDpi()?->getNumDossier(),
            $patient?->getFullName(),
            $visite->getService()?->getLibelle(),
            $visite->getStatut(),
            $visite->getEnterAt()?->format('d/m/Y H:i'),
            $visite->getSortedAt()?->format('d/m/Y H:i'),
            $visite->getSortedPrevuAt()?->format('d/m/Y H:i'),
            $lit?->getCode(),
            $lit?->getChambre()?->getLibelle(),
            $lit?->getChambre()?->getBloc()?->getLibelle(),
        ];
    }

    public function create(CreateVisiteInput $input): Visite
    {
        $this->assertCanPerformTriage();

        $errors = $this->validator->validate($input);
        if (count($errors) > 0) {
            throw new ValidationFailedException($input, $errors);
        }

        $dpi = $this->getDpiById($input->dpiId);
        $this->assertDpiAllowsVisite($dpi);

        if ($this->visiteRepository->countActiveByDpi($dpi->getId()) > 0) {
            throw new ConflictException('Ce dossier patient possède déjà une visite active.');
        }

        $service = $this->getServiceById($input->serviceId);
        $this->assertServiceAccessible($service);

        $typeEntree = Triage::normalizeTypeEntree($input->typeEntree);
        $signesTriage = $this->signeVitalRepository->findForTriage();
        $mesuresBySigneId = $this->resolveTriageMesures($input->signesVitaux, $signesTriage);

        $statut = Triage::resolveInitialVisiteStatut($typeEntree);
        $now = new \DateTimeImmutable();

        $visite = (new Visite())
            ->setDpi($dpi)
            ->setService($service)
            ->setStatut($statut)
            ->setEnterAt($now)
            ->setSortedPrevuAt($this->parseOptionalDateTime($input->sortedPrevuAt));

        $triage = (new Triage())
            ->setTypeEntree($typeEntree)
            ->setPriorite($input->priorite)
            ->setMotif(trim($input->motif))
            ->setTriagedAt($now)
            ->setTriagedBy($this->resolveCurrentPersonnel());

        foreach ($mesuresBySigneId as $mesureData) {
            $triage->addMesure(
                (new TriageMesure())
                    ->setSigneVital($mesureData['signeVital'])
                    ->setValeur($mesureData['valeur']),
            );
        }

        $visite->setTriage($triage);
        $this->entityManager->persist($visite);

        if (Triage::TYPE_CONSULTATION === $typeEntree) {
            $this->consultationService->createInitialFromTriage(
                $visite,
                trim($input->motif),
                $this->resolveCurrentPersonnel(),
                $now,
            );
        }

        $this->persistConsultationActe($visite);
        $this->entityManager->flush();

        return $visite;
    }

    public function update(int $id, UpdateVisiteInput $input): Visite
    {
        $errors = $this->validator->validate($input);
        if (count($errors) > 0) {
            throw new ValidationFailedException($input, $errors);
        }

        $visite = $this->getById($id);
        $this->assertVisiteAccessible($visite);

        if (in_array($visite->getStatut(), [Visite::STATUT_TERMINEE, Visite::STATUT_ANNULEE], true)) {
            throw new ConflictException('Une visite terminée ou annulée ne peut plus être modifiée.');
        }

        $targetStatut = null !== $input->statut && '' !== trim($input->statut)
            ? Visite::normalizeStatut($input->statut)
            : null;
        $isClosingVisit = in_array($targetStatut, [Visite::STATUT_TERMINEE, Visite::STATUT_ANNULEE], true);
        if (!$isClosingVisit) {
            $this->assertRecordWritable($visite);
        }

        if (null !== $input->serviceId) {
            $service = $this->getServiceById($input->serviceId);
            $this->assertServiceAccessible($service);
            $visite->setService($service);
        }

        if (null !== $input->sortedPrevuAt) {
            $visite->setSortedPrevuAt('' === trim($input->sortedPrevuAt) ? null : $this->parseOptionalDateTime($input->sortedPrevuAt));
        }

        if (null !== $targetStatut && $targetStatut !== $visite->getStatut()) {
            $this->applyStatutTransition($visite, $targetStatut, $input->litId);
        } elseif (Visite::STATUT_HOSPITALISE === $visite->getStatut() && null !== $input->litId) {
            $visite->setLit($this->resolveLitForHospitalisation($input->litId, $visite->getId()));
        }

        $this->entityManager->flush();

        return $visite;
    }

    public function delete(int $id): void
    {
        $visite = $this->getById($id);
        $this->assertVisiteAccessible($visite);
        $this->assertDeletable($visite);

        $this->entityManager->remove($visite);
        $this->entityManager->flush();
    }

    public function getById(int $id): Visite
    {
        $visite = $this->visiteRepository->find($id);
        if (null === $visite) {
            throw new NotFoundException('Visite non trouvée.');
        }

        return $visite;
    }

    /** @return array<string, mixed> */
    public function serializeSummary(Visite $visite, ?bool $pendingHospitalization = null): array
    {
        $dpi = $visite->getDpi();
        $patient = $dpi?->getPatient();
        $service = $visite->getService();
        $lit = $visite->getLit();
        $chambre = $lit?->getChambre();
        $bloc = $chambre?->getBloc();

        return [
            'id' => $visite->getId(),
            'statut' => $visite->getStatut(),
            'enterAt' => $visite->getEnterAt()?->format(\DateTimeInterface::ATOM),
            'sortedAt' => $visite->getSortedAt()?->format(\DateTimeInterface::ATOM),
            'sortedPrevuAt' => $visite->getSortedPrevuAt()?->format(\DateTimeInterface::ATOM),
            'hospitalizedAt' => $visite->getHospitalizedAt()?->format(\DateTimeInterface::ATOM),
            'isHospitalization' => $visite->isHospitalization(),
            'isCurrentHospitalization' => $visite->isCurrentHospitalization(),
            'allowedTransitions' => Visite::getAllowedTransitions((string) $visite->getStatut()),
            'dpiId' => $dpi?->getId(),
            'numDossier' => $dpi?->getNumDossier(),
            'patientId' => null !== $patient?->getId() ? (string) $patient->getId() : null,
            'patientName' => $patient?->getFullName(),
            'serviceId' => $service?->getId(),
            'service' => null !== $service ? [
                'id' => $service->getId(),
                'code' => $service->getCode(),
                'libelle' => $service->getLibelle(),
            ] : null,
            'litId' => $lit?->getId(),
            'lit' => null !== $lit ? [
                'id' => $lit->getId(),
                'code' => $lit->getCode(),
                'numeroLit' => $lit->getNumeroLit(),
                'chambre' => null !== $chambre ? $chambre->getLibelle() : null,
                'bloc' => null !== $bloc ? $bloc->getLibelle() : null,
            ] : null,
            'consultationCount' => $visite->getConsultations()->count(),
            'acteFinancierCount' => $visite->getActeFinancierVisites()->count(),
            'pendingHospitalization' => $pendingHospitalization ?? $this->isPendingHospitalization($visite),
            'patientStatus' => $patient?->getStatus(),
            'dpiStatut' => $dpi?->getStatut(),
            'recordWritable' => $this->isRecordWritable($visite),
            'triage' => $this->serializeTriageSummary($visite->getTriage()),
        ];
    }

    /** @return array<string, mixed> */
    public function serializeDetail(Visite $visite): array
    {
        $detail = $this->serializeSummary($visite);
        $detail['triage'] = $this->serializeTriageDetail($visite->getTriage());

        return $detail;
    }

    /** @return array<string, mixed> */
    public function buildCreateMeta(): array
    {
        $services = $this->listServiceOptions();
        $departements = [];
        $seenDepartementIds = [];

        foreach ($services as $service) {
            $departementId = $service['departementId'] ?? null;
            if (null === $departementId || isset($seenDepartementIds[$departementId])) {
                continue;
            }

            $seenDepartementIds[$departementId] = true;
            $departements[] = [
                'id' => $departementId,
                'code' => $service['departementCode'],
                'libelle' => $service['departement'],
            ];
        }

        usort(
            $departements,
            static fn (array $left, array $right): int => strcmp((string) $left['libelle'], (string) $right['libelle']),
        );

        return [
            'typesEntree' => array_map(
                static fn (string $type): array => [
                    'value' => $type,
                    'label' => match ($type) {
                        Triage::TYPE_RENDEZ_VOUS => 'Rendez-vous',
                        Triage::TYPE_URGENCE => 'Urgence',
                        default => 'Consultation',
                    },
                ],
                Triage::getTypesEntree(),
            ),
            'prioriteOptions' => [
                ['value' => 1, 'label' => '1 — Très urgent'],
                ['value' => 2, 'label' => '2 — Urgent'],
                ['value' => 3, 'label' => '3 — Normal'],
                ['value' => 4, 'label' => '4 — Peu urgent'],
                ['value' => 5, 'label' => '5 — Non urgent'],
            ],
            'departements' => $departements,
            'services' => $services,
            'canReadSignesVitaux' => $this->canReadSignesVitaux(),
            'signesVitaux' => $this->canReadSignesVitaux()
                ? array_map([$this, 'serializeSigneVitalOption'], $this->signeVitalRepository->findForTriage())
                : [],
        ];
    }

    /** @return list<array{id: int, code: string, libelle: string, departement: string|null}> */
    public function listServiceOptions(): array
    {
        $services = $this->serviceRepository->findBy([], ['libelle' => 'ASC']);
        $accessScope = $this->resolveAccessScope();

        return array_values(array_map(
            static fn (Service $service): array => [
                'id' => $service->getId(),
                'code' => $service->getCode(),
                'libelle' => $service->getLibelle(),
                'departementId' => $service->getDepartement()?->getId(),
                'departementCode' => $service->getDepartement()?->getCode(),
                'departement' => $service->getDepartement()?->getLibelle(),
            ],
            array_filter(
                $services,
                fn (Service $service): bool => $accessScope->allowsService($service),
            ),
        ));
    }

    /** @return array{blocs: list<array<string, mixed>>} */
    public function buildHospitalisationMeta(?int $excludeVisiteId = null): array
    {
        $lits = $this->litRepository->findAllWithChambreAndBloc();
        /** @var array<int, array{id: int, code: string, libelle: string, chambres: array<int, array{id: int, code: string, libelle: string, lits: list<array{id: int, code: string, numeroLit: string}>}>}> $blocs */
        $blocs = [];

        foreach ($lits as $lit) {
            $chambre = $lit->getChambre();
            $bloc = $chambre?->getBloc();
            if (null === $chambre || null === $bloc) {
                continue;
            }

            $litId = (int) $lit->getId();
            $occupied = null !== $this->visiteRepository->findActiveHospitalisationByLit($litId, $excludeVisiteId);
            if ($occupied) {
                continue;
            }

            $blocId = (int) $bloc->getId();
            $chambreId = (int) $chambre->getId();

            if (!isset($blocs[$blocId])) {
                $blocs[$blocId] = [
                    'id' => $blocId,
                    'code' => $bloc->getCode(),
                    'libelle' => $bloc->getLibelle(),
                    'chambres' => [],
                ];
            }

            if (!isset($blocs[$blocId]['chambres'][$chambreId])) {
                $blocs[$blocId]['chambres'][$chambreId] = [
                    'id' => $chambreId,
                    'code' => $chambre->getCode(),
                    'libelle' => $chambre->getLibelle(),
                    'lits' => [],
                ];
            }

            $blocs[$blocId]['chambres'][$chambreId]['lits'][] = [
                'id' => $litId,
                'code' => $lit->getCode(),
                'numeroLit' => $lit->getNumeroLit(),
            ];
        }

        $normalizedBlocs = array_values(array_map(
            static function (array $bloc): array {
                $bloc['chambres'] = array_values($bloc['chambres']);

                return $bloc;
            },
            $blocs,
        ));

        return ['blocs' => $normalizedBlocs];
    }

    /** @return list<array{id: int, code: string, numeroLit: string, chambreId: int|null, chambre: string|null, chambreCode: string|null, blocId: int|null, bloc: string|null, blocCode: string|null, occupied: bool}> */
    public function listLitOptions(): array
    {
        $lits = $this->litRepository->findAllWithChambreAndBloc();

        return array_map(function (Lit $lit): array {
            $chambre = $lit->getChambre();
            $bloc = $chambre?->getBloc();
            $occupied = null !== $this->visiteRepository->findActiveHospitalisationByLit((int) $lit->getId());

            return [
                'id' => $lit->getId(),
                'code' => $lit->getCode(),
                'numeroLit' => $lit->getNumeroLit(),
                'chambreId' => $chambre?->getId(),
                'chambre' => $chambre?->getLibelle(),
                'chambreCode' => $chambre?->getCode(),
                'blocId' => $bloc?->getId(),
                'bloc' => $bloc?->getLibelle(),
                'blocCode' => $bloc?->getCode(),
                'occupied' => $occupied,
            ];
        }, $lits);
    }

    private function applyStatutTransition(Visite $visite, string $targetStatut, ?int $litId): void
    {
        $currentStatut = (string) $visite->getStatut();
        if (!Visite::canTransition($currentStatut, $targetStatut)) {
            throw new ConflictException(sprintf(
                'Transition de statut impossible : %s → %s.',
                $currentStatut,
                $targetStatut,
            ));
        }

        if (Visite::STATUT_HOSPITALISE === $targetStatut) {
            $visite->setLit($this->resolveLitForHospitalisation($litId ?? $visite->getLit()?->getId(), $visite->getId()));
            if (null === $visite->getHospitalizedAt()) {
                $visite->setHospitalizedAt(new \DateTimeImmutable());
            }
        }

        if (Visite::STATUT_TERMINEE === $targetStatut) {
            $visite->setSortedAt(new \DateTimeImmutable());
        }

        if (Visite::STATUT_EN_COURS === $targetStatut && Visite::STATUT_PLANIFIEE === $currentStatut) {
            $visite->setEnterAt(new \DateTimeImmutable());
        }

        if (Visite::STATUT_ANNULEE === $targetStatut) {
            $visite->setLit(null);
        }

        $visite->setStatut($targetStatut);
    }

    private function resolveLitForHospitalisation(?int $litId, ?int $excludeVisiteId): Lit
    {
        if (null === $litId) {
            throw new ConflictException('Un lit est obligatoire pour une hospitalisation.');
        }

        $lit = $this->litRepository->find($litId);
        if (null === $lit) {
            throw new NotFoundException('Lit non trouvé.');
        }

        $occupant = $this->visiteRepository->findActiveHospitalisationByLit($litId, $excludeVisiteId);
        if (null !== $occupant) {
            throw new ConflictException('Ce lit est déjà occupé par une autre hospitalisation active.');
        }

        return $lit;
    }

    private function assertDpiAllowsVisite(Dpi $dpi): void
    {
        $this->assertRecordWritableFromDpi($dpi);
    }

    private function assertRecordWritable(Visite $visite): void
    {
        $this->assertRecordWritableFromDpi($visite->getDpi());
    }

    private function assertRecordWritableFromDpi(?Dpi $dpi): void
    {
        if (null === $dpi) {
            throw new ConflictException('Dossier patient introuvable.');
        }

        if (Dpi::STATUT_OUVERT !== Dpi::normalizeStatut((string) $dpi->getStatut())) {
            throw new ConflictException('Le dossier patient doit être ouvert pour cette action.');
        }

        $patient = $dpi->getPatient();
        if (null !== $patient && !$patient->isClinicallyWritable()) {
            if ($patient->isDeceased()) {
                throw new ConflictException('Impossible de modifier le parcours d\'un patient décédé.');
            }

            throw new ConflictException('Ce patient est inactif. Réactivez-le pour modifier le parcours.');
        }
    }

    private function isRecordWritable(Visite $visite): bool
    {
        $dpi = $visite->getDpi();
        $patient = $dpi?->getPatient();

        if (null === $dpi || Dpi::STATUT_OUVERT !== Dpi::normalizeStatut((string) $dpi->getStatut())) {
            return false;
        }

        return null === $patient || $patient->isClinicallyWritable();
    }

    private function assertDeletable(Visite $visite): void
    {
        if (!$visite->getConsultations()->isEmpty() || !$visite->getActeFinancierVisites()->isEmpty()) {
            throw new ConflictException('Cette visite possède des consultations ou actes financiers et ne peut pas être supprimée.');
        }

        if ($visite->isActive()) {
            throw new ConflictException('Seules les visites terminées ou annulées sans données liées peuvent être supprimées.');
        }
    }

    private function getDpiById(int $dpiId): Dpi
    {
        $dpi = $this->dpiRepository->find($dpiId);
        if (null === $dpi) {
            throw new NotFoundException('Dossier patient (DPI) non trouvé.');
        }

        return $dpi;
    }

    private function getServiceById(int $serviceId): Service
    {
        $service = $this->serviceRepository->find($serviceId);
        if (null === $service) {
            throw new NotFoundException('Service non trouvé.');
        }

        return $service;
    }

    private function assertServiceAccessible(Service $service): void
    {
        $accessScope = $this->resolveAccessScope();
        if (!$accessScope->allowsService($service)) {
            throw new ConflictException('Vous n\'avez pas accès à ce service.');
        }
    }

    private function assertVisiteAccessible(Visite $visite): void
    {
        $service = $visite->getService();
        if (null === $service) {
            return;
        }

        $this->assertServiceAccessible($service);
    }

    private function resolveAccessScope(): PersonnelAccessScope
    {
        $viewer = $this->security->getUser();
        if (!$viewer instanceof Personnel) {
            return new PersonnelAccessScope(unrestricted: true);
        }

        return $this->permissionChecker->resolveAccessScope($viewer, CliniquePermissions::VISITE_READ);
    }

    private function parseOptionalDateTime(?string $value): ?\DateTimeImmutable
    {
        if (null === $value || '' === trim($value)) {
            return null;
        }

        $date = \DateTimeImmutable::createFromFormat(\DateTimeInterface::ATOM, trim($value));
        if (false === $date) {
            throw new ConflictException('Date invalide.');
        }

        return $date;
    }

    private function parseDateStart(?string $value): ?\DateTimeImmutable
    {
        if (null === $value || '' === trim($value)) {
            return null;
        }

        $date = \DateTimeImmutable::createFromFormat('Y-m-d', trim($value));
        if (false === $date) {
            throw new ConflictException('Date de début invalide.');
        }

        return $date->setTime(0, 0, 0);
    }

    private function parseDateEndExclusive(?string $value): ?\DateTimeImmutable
    {
        if (null === $value || '' === trim($value)) {
            return null;
        }

        $date = \DateTimeImmutable::createFromFormat('Y-m-d', trim($value));
        if (false === $date) {
            throw new ConflictException('Date de fin invalide.');
        }

        return $date->modify('+1 day')->setTime(0, 0, 0);
    }

    private function assertCanPerformTriage(): void
    {
        if (!$this->canReadSignesVitaux()) {
            throw new AccessDeniedException('Permission requise pour lire les signes vitaux au triage.');
        }
    }

    private function canReadSignesVitaux(): bool
    {
        $viewer = $this->security->getUser();
        if (!$viewer instanceof Personnel) {
            return true;
        }

        return $this->permissionChecker->isGranted($viewer, ReferentielPermissions::SIGNE_VITAL_READ);
    }

    private function resolveCurrentPersonnel(): ?Personnel
    {
        $viewer = $this->security->getUser();

        return $viewer instanceof Personnel ? $viewer : null;
    }

    /**
     * @param list<TriageSigneVitalInput> $inputs
     * @param list<SigneVital>            $signesTriage
     *
     * @return array<int, array{signeVital: SigneVital, valeur: string}>
     */
    private function resolveTriageMesures(array $inputs, array $signesTriage): array
    {
        if ([] === $signesTriage) {
            return [];
        }

        $signesById = [];
        foreach ($signesTriage as $signeVital) {
            $signesById[(int) $signeVital->getId()] = $signeVital;
        }

        $mesuresBySigneId = [];
        $seenSigneIds = [];

        foreach ($inputs as $input) {
            if (!$input instanceof TriageSigneVitalInput) {
                $input = new TriageSigneVitalInput(
                    signeVitalId: (int) ($input['signeVitalId'] ?? 0),
                    valeur: (string) ($input['valeur'] ?? ''),
                );
            }

            $inputErrors = $this->validator->validate($input);
            if (count($inputErrors) > 0) {
                throw new ValidationFailedException($input, $inputErrors);
            }

            $signeVitalId = $input->signeVitalId;
            if (!isset($signesById[$signeVitalId])) {
                throw new ConflictException('Signe vital invalide ou non autorisé au triage.');
            }

            if (isset($seenSigneIds[$signeVitalId])) {
                throw new ConflictException('Chaque signe vital ne peut être saisi qu\'une seule fois.');
            }

            $valeur = trim($input->valeur);
            if ('' === $valeur) {
                continue;
            }

            $seenSigneIds[$signeVitalId] = true;
            $mesuresBySigneId[$signeVitalId] = [
                'signeVital' => $signesById[$signeVitalId],
                'valeur' => $valeur,
            ];
        }

        foreach ($signesTriage as $signeVital) {
            if (!$signeVital->isObligatoireAuTriage()) {
                continue;
            }

            $signeVitalId = (int) $signeVital->getId();
            if (!isset($mesuresBySigneId[$signeVitalId])) {
                throw new ConflictException(sprintf(
                    'Le signe vital « %s » est obligatoire au triage.',
                    $signeVital->getLibelle(),
                ));
            }
        }

        return $mesuresBySigneId;
    }

    private function persistConsultationActe(Visite $visite): void
    {
        $acte = $this->acteFinancierRepository->findOneBy([
            'code' => ActeFinancier::CODE_CONSULTATION,
            'statut' => ActeFinancier::STATUT_ACTIF,
        ]);

        if (null === $acte) {
            throw new ConflictException('L\'acte financier CONSULTATION est introuvable. Exécutez app:facturation:seed-defaults.');
        }

        $tarif = (string) $acte->getTarif();
        $acteVisite = (new ActeFinancierVisite())
            ->setVisite($visite)
            ->setActe($acte)
            ->setQuantite(1)
            ->setTarifUnitaire($tarif)
            ->setTarifTotal($tarif)
            ->setStatut(ActeFinancierVisite::STATUT_EN_ATTENTE)
            ->setCreatedAt(new \DateTimeImmutable());

        $this->entityManager->persist($acteVisite);
    }

    /** @return array<string, mixed>|null */
    private function serializeTriageSummary(?Triage $triage): ?array
    {
        if (null === $triage) {
            return null;
        }

        return [
            'id' => $triage->getId(),
            'typeEntree' => $triage->getTypeEntree(),
            'priorite' => $triage->getPriorite(),
            'motif' => $triage->getMotif(),
            'triagedAt' => $triage->getTriagedAt()?->format(\DateTimeInterface::ATOM),
        ];
    }

    /** @return array<string, mixed>|null */
    private function serializeTriageDetail(?Triage $triage): ?array
    {
        $summary = $this->serializeTriageSummary($triage);
        if (null === $summary) {
            return null;
        }

        $personnel = $triage->getTriagedBy();
        $summary['triagedBy'] = null !== $personnel ? [
            'id' => (string) $personnel->getId(),
            'fullName' => trim(sprintf(
                '%s %s %s',
                $personnel->getPrenom() ?? '',
                $personnel->getNom() ?? '',
                $personnel->getPostNom() ?? '',
            )),
        ] : null;
        $summary['mesures'] = array_map(
            static fn (TriageMesure $mesure): array => [
                'signeVitalId' => $mesure->getSigneVital()?->getId(),
                'code' => $mesure->getSigneVital()?->getCode(),
                'libelle' => $mesure->getSigneVital()?->getLibelle(),
                'unite' => $mesure->getSigneVital()?->getUnite(),
                'valeur' => $mesure->getValeur(),
            ],
            $triage->getMesures()->toArray(),
        );

        return $summary;
    }

    /** @return array<string, mixed> */
    private function serializeSigneVitalOption(SigneVital $signeVital): array
    {
        return [
            'id' => $signeVital->getId(),
            'code' => $signeVital->getCode(),
            'libelle' => $signeVital->getLibelle(),
            'unite' => $signeVital->getUnite(),
            'obligatoireAuTriage' => $signeVital->isObligatoireAuTriage(),
            'ordre' => $signeVital->getOrdre(),
        ];
    }

    private function isPendingHospitalization(Visite $visite): bool
    {
        if (Visite::STATUT_EN_COURS !== $visite->getStatut()) {
            return false;
        }

        foreach ($visite->getConsultations() as $consultation) {
            if (true === $consultation->getNeedsHospitalization()) {
                return true;
            }
        }

        return false;
    }
}
