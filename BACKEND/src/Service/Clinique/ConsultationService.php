<?php

namespace App\Service\Clinique;

use App\DTO\Clinique\CloseConsultationInput;
use App\DTO\Clinique\ConsultationListQuery;
use App\DTO\Clinique\CreateConsultationInput;
use App\DTO\Clinique\CreateVisiteMesureInput;
use App\DTO\Clinique\CreateVisiteMesurePriseInput;
use App\DTO\Clinique\UpdateConsultationInput;
use App\DTO\Common\PaginatedResult;
use App\DTO\Patient\AntecedentListQuery;
use App\DTO\Patient\CreateAntecedentInput;
use App\Entity\Consultation;
use App\Entity\Dpi;
use App\Entity\Personnel;
use App\Entity\SigneVital;
use App\Entity\Triage;
use App\Entity\TriageMesure;
use App\Entity\Visite;
use App\Entity\VisiteMesure;
use App\Exception\ConflictException;
use App\Exception\NotFoundException;
use App\Repository\ConsultationRepository;
use App\Repository\SigneVitalRepository;
use App\Repository\VisiteMesureRepository;
use App\Repository\VisiteRepository;
use App\Security\Permission\CliniquePermissions;
use App\Security\PermissionChecker;
use App\Security\PersonnelAccessScope;
use App\Service\Patient\AntecedentService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Validator\Exception\ValidationFailedException;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final class ConsultationService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly ConsultationRepository $consultationRepository,
        private readonly VisiteRepository $visiteRepository,
        private readonly VisiteMesureRepository $visiteMesureRepository,
        private readonly SigneVitalRepository $signeVitalRepository,
        private readonly PhysicalExamNormalizer $physicalExamNormalizer,
        private readonly EvolutionSheetNormalizer $evolutionSheetNormalizer,
        private readonly AntecedentService $antecedentService,
        private readonly ValidatorInterface $validator,
        private readonly PermissionChecker $permissionChecker,
        private readonly Security $security,
    ) {
    }

    public function paginate(ConsultationListQuery $query): PaginatedResult
    {
        $errors = $this->validator->validate($query);
        if (count($errors) > 0) {
            throw new ValidationFailedException($query, $errors);
        }

        $result = $this->consultationRepository->paginate(
            $query->page,
            $query->limit,
            $query->search,
            $query->statut,
            $query->visiteId,
            $query->patientId,
            $this->resolveAccessScope(),
        );

        return new PaginatedResult(
            array_map([$this, 'serializeSummary'], $result['items']),
            $query->page,
            $query->limit,
            $result['total'],
        );
    }

    /**
     * Crée la consultation initiale lors d'un triage de type CONSULTATION.
     */
    public function createInitialFromTriage(
        Visite $visite,
        ?string $motif = null,
        ?Personnel $openedBy = null,
        ?\DateTimeImmutable $at = null,
    ): Consultation {
        if ($visite->getConsultations()->count() > 0) {
            throw new ConflictException('Cette visite possède déjà une consultation.');
        }

        $triage = $visite->getTriage();
        if (null === $triage || Triage::TYPE_CONSULTATION !== $triage->getTypeEntree()) {
            throw new ConflictException('Seul un triage de type CONSULTATION génère une consultation automatique.');
        }

        $at ??= $visite->getEnterAt() ?? $triage->getTriagedAt() ?? new \DateTimeImmutable();
        $motif ??= $triage->getMotif();
        $openedBy ??= $triage->getTriagedBy();

        $statut = $this->resolveInitialConsultationStatut($visite);

        $consultation = (new Consultation())
            ->setVisite($visite)
            ->setOpenedBy($openedBy)
            ->setMotif($this->normalizeOptionalText($motif))
            ->setTypeConsultation($this->resolveDefaultType($visite))
            ->setStatut($statut)
            ->setConsultedAt($at);

        if (Consultation::STATUT_EN_COURS === $statut) {
            $consultation->setDebutAt($at);
        }

        if (in_array($statut, [Consultation::STATUT_TERMINEE, Consultation::STATUT_ANNULEE], true)) {
            $consultation->setFinAt($at);
        }

        $this->entityManager->persist($consultation);

        return $consultation;
    }

    /**
     * Rattrape les consultations manquantes pour les visites triées en CONSULTATION.
     */
    public function backfillMissingFromTriage(): int
    {
        $visites = $this->visiteRepository->findWithConsultationTriageMissingConsultation();
        $count = 0;

        foreach ($visites as $visite) {
            $this->createInitialFromTriage($visite);
            ++$count;
        }

        if ($count > 0) {
            $this->entityManager->flush();
        }

        return $count;
    }

    public function create(CreateConsultationInput $input): Consultation
    {
        $errors = $this->validator->validate($input);
        if (count($errors) > 0) {
            throw new ValidationFailedException($input, $errors);
        }

        $personnel = $this->resolveCurrentPersonnel();
        if (null === $personnel) {
            throw new ConflictException('Utilisateur personnel requis pour ouvrir une consultation.');
        }

        $visite = $this->getVisiteById((int) $input->visiteId);
        $this->assertVisiteAccessible($visite);
        $this->assertRecordWritable($visite);
        $this->assertVisiteAllowsConsultation($visite);

        if ($this->consultationRepository->countActiveByVisite((int) $visite->getId()) > 0) {
            throw new ConflictException('Cette visite possède déjà une consultation active.');
        }

        $now = new \DateTimeImmutable();
        $statut = null !== $input->statut && '' !== trim($input->statut)
            ? Consultation::normalizeStatut($input->statut)
            : Consultation::STATUT_EN_COURS;

        $type = null !== $input->typeConsultation && '' !== trim($input->typeConsultation)
            ? Consultation::normalizeType($input->typeConsultation)
            : $this->resolveDefaultType($visite);
        $this->assertTypeMatchesVisite($visite, (string) $type);

        $consultation = (new Consultation())
            ->setVisite($visite)
            ->setOpenedBy($personnel)
            ->setTypeConsultation($type)
            ->setMotif($this->normalizeOptionalText($input->motif))
            ->setStatut($statut)
            ->setConsultedAt($now);

        if (Consultation::STATUT_EN_COURS === $statut) {
            $consultation->setDebutAt($now);
        }

        $this->entityManager->persist($consultation);
        $this->entityManager->flush();

        return $consultation;
    }

    public function update(int $id, UpdateConsultationInput $input): Consultation
    {
        $errors = $this->validator->validate($input);
        if (count($errors) > 0) {
            throw new ValidationFailedException($input, $errors);
        }

        $consultation = $this->getById($id);
        $this->assertConsultationAccessible($consultation);
        $this->assertConsultationWritable($consultation);

        $currentStatut = (string) $consultation->getStatut();
        $targetStatut = null !== $input->statut && '' !== trim($input->statut)
            ? Consultation::normalizeStatut($input->statut)
            : null;

        $hasClinicalChange = null !== $input->typeConsultation
            || null !== $input->motif
            || null !== $input->histoireMaladie
            || null !== $input->physicalExamText
            || null !== $input->conduireATenir
            || null !== $input->complementAnamnese
            || null !== $input->physicalExam
            || null !== $input->evolutionSheet;

        if ($hasClinicalChange && !Consultation::isEditableStatut($currentStatut)) {
            throw new ConflictException('Une consultation terminée ou annulée ne peut plus être modifiée.');
        }

        if (null !== $input->typeConsultation) {
            $type = '' === trim($input->typeConsultation)
                ? null
                : Consultation::normalizeType($input->typeConsultation);
            if (null !== $type && !Consultation::isValidCreatableType($type)) {
                throw new ConflictException('Type de consultation invalide.');
            }
            if (null !== $type) {
                $this->assertTypeMatchesVisite($consultation->getVisite(), $type);
            }
            $consultation->setTypeConsultation($type);
        }

        if (null !== $input->motif) {
            $consultation->setMotif($this->normalizeOptionalText($input->motif));
        }

        if (null !== $input->histoireMaladie) {
            $consultation->setHistoireMaladie($this->normalizeOptionalText($input->histoireMaladie));
        }

        if (null !== $input->physicalExamText) {
            $consultation->setPhysicalExamText($this->normalizeOptionalText($input->physicalExamText));
        }

        if (null !== $input->conduireATenir) {
            $consultation->setConduireATenir($this->normalizeOptionalText($input->conduireATenir));
        }

        if (null !== $input->complementAnamnese) {
            $consultation->setComplementAnamnese($this->normalizeAnamneseList($input->complementAnamnese));
        }

        if (null !== $input->physicalExam) {
            $consultation->setPhysicalExam($this->physicalExamNormalizer->normalize($input->physicalExam));
        }

        if (null !== $input->evolutionSheet) {
            $consultation->setEvolutionSheet($this->evolutionSheetNormalizer->normalize($input->evolutionSheet));
        }

        if (null !== $targetStatut && $targetStatut !== $currentStatut) {
            $this->applyStatutTransition($consultation, $targetStatut);
        }

        $this->entityManager->flush();

        return $consultation;
    }

    public function close(int $id, CloseConsultationInput $input): Consultation
    {
        $errors = $this->validator->validate($input);
        if (count($errors) > 0) {
            throw new ValidationFailedException($input, $errors);
        }

        $consultation = $this->getById($id);
        $this->assertConsultationAccessible($consultation);
        $this->assertConsultationWritable($consultation, 'Cette consultation est déjà clôturée.');

        $personnel = $this->resolveCurrentPersonnel();
        if (null === $personnel) {
            throw new ConflictException('Utilisateur personnel requis pour clôturer une consultation.');
        }

        $visite = $consultation->getVisite();
        $alreadyHospitalized = Visite::STATUT_HOSPITALISE === $visite?->getStatut();
        if ($alreadyHospitalized && $input->needsHospitalization) {
            throw new ConflictException('Ce patient est déjà hospitalisé. Clôturez le tour de salle ou préparez la sortie.');
        }

        $needsHospitalization = !$alreadyHospitalized && $input->needsHospitalization;
        $dischargePatient = $alreadyHospitalized && $input->dischargePatient;
        $wantsAppointment = !$needsHospitalization && $input->wantsAppointment;

        $consultation
            ->setNeedsHospitalization($needsHospitalization)
            ->setWantsAppointment($wantsAppointment)
            ->setNextAppointmentAt(
                $wantsAppointment
                    ? $this->parseOptionalDateTime($input->nextAppointmentAt)
                    : null,
            )
            ->setHospitalizationPatientOpinion(
                $needsHospitalization
                    ? strtoupper(trim((string) $input->hospitalizationPatientOpinion))
                    : null,
            )
            ->setHospitalizationObservation(
                $needsHospitalization && Consultation::OPINION_NON_FAVORABLE === strtoupper(trim((string) $input->hospitalizationPatientOpinion))
                    ? $this->normalizeOptionalText($input->hospitalizationObservation)
                    : null,
            )
            ->setClosedBy($personnel);

        $this->applyStatutTransition($consultation, Consultation::STATUT_TERMINEE);
        $this->applyVisiteOrientationAfterClose($consultation, $dischargePatient);

        $this->entityManager->flush();

        return $consultation;
    }

    /** @return array<string, mixed> */
    public function getVitalsContext(int $consultationId): array
    {
        $consultation = $this->getById($consultationId);
        $this->assertConsultationAccessible($consultation);

        $visite = $consultation->getVisite();
        if (null === $visite) {
            throw new NotFoundException('Visite introuvable pour cette consultation.');
        }

        return [
            'triageMesures' => $this->serializeTriageMesures($visite->getTriage()),
            'consultationMesures' => array_map(
                [$this, 'serializeVisiteMesure'],
                $this->visiteMesureRepository->findByVisite((int) $visite->getId()),
            ),
            'signesVitaux' => array_map(
                static fn (SigneVital $signe): array => [
                    'id' => $signe->getId(),
                    'code' => $signe->getCode(),
                    'libelle' => $signe->getLibelle(),
                    'unite' => $signe->getUnite(),
                ],
                $this->signeVitalRepository->findActifs(),
            ),
        ];
    }

    /** @return array<string, mixed> */
    public function addVisiteMesurePrise(int $consultationId, CreateVisiteMesurePriseInput $input): array
    {
        $errors = $this->validator->validate($input);
        if (count($errors) > 0) {
            throw new ValidationFailedException($input, $errors);
        }

        $consultation = $this->getById($consultationId);
        $this->assertConsultationAccessible($consultation);
        $this->assertConsultationWritable($consultation, 'Impossible d\'ajouter des constantes sur une consultation clôturée.');

        $visite = $consultation->getVisite();
        if (null === $visite) {
            throw new NotFoundException('Visite introuvable.');
        }

        $measuredAt = new \DateTimeImmutable();
        $personnel = $this->resolveCurrentPersonnel();
        $added = 0;

        foreach ($input->mesures as $row) {
            if (!is_array($row)) {
                continue;
            }

            $signeVitalId = (int) ($row['signeVitalId'] ?? 0);
            $valeur = trim((string) ($row['valeur'] ?? ''));
            if ($signeVitalId <= 0 || '' === $valeur) {
                continue;
            }

            $signeVital = $this->signeVitalRepository->find($signeVitalId);
            if (null === $signeVital || SigneVital::STATUT_ACTIF !== $signeVital->getStatut()) {
                throw new NotFoundException('Signe vital non trouvé.');
            }

            $mesure = (new VisiteMesure())
                ->setVisite($visite)
                ->setSigneVital($signeVital)
                ->setValeur($valeur)
                ->setSource(VisiteMesure::SOURCE_CONSULTATION)
                ->setMeasuredAt($measuredAt)
                ->setMeasuredBy($personnel);

            $this->entityManager->persist($mesure);
            ++$added;
        }

        if (0 === $added) {
            throw new ConflictException('Saisissez au moins une valeur.');
        }

        $this->entityManager->flush();

        return $this->getVitalsContext($consultationId);
    }

    public function addVisiteMesure(int $consultationId, CreateVisiteMesureInput $input): VisiteMesure
    {
        $errors = $this->validator->validate($input);
        if (count($errors) > 0) {
            throw new ValidationFailedException($input, $errors);
        }

        $consultation = $this->getById($consultationId);
        $this->assertConsultationAccessible($consultation);
        $this->assertConsultationWritable($consultation, 'Impossible d\'ajouter des constantes sur une consultation clôturée.');

        $visite = $consultation->getVisite();
        if (null === $visite) {
            throw new NotFoundException('Visite introuvable.');
        }

        $signeVital = $this->signeVitalRepository->find((int) $input->signeVitalId);
        if (null === $signeVital || SigneVital::STATUT_ACTIF !== $signeVital->getStatut()) {
            throw new NotFoundException('Signe vital non trouvé.');
        }

        $mesure = (new VisiteMesure())
            ->setVisite($visite)
            ->setSigneVital($signeVital)
            ->setValeur(trim($input->valeur))
            ->setSource(VisiteMesure::SOURCE_CONSULTATION)
            ->setMeasuredAt(new \DateTimeImmutable())
            ->setMeasuredBy($this->resolveCurrentPersonnel());

        $this->entityManager->persist($mesure);
        $this->entityManager->flush();

        return $mesure;
    }

    public function paginateAntecedents(int $consultationId, AntecedentListQuery $query): PaginatedResult
    {
        $consultation = $this->getById($consultationId);
        $this->assertConsultationAccessible($consultation);

        $dpi = $this->resolveDpiFromConsultation($consultation);

        return $this->antecedentService->paginateByDpi((int) $dpi->getId(), $query);
    }

    /** @return array<string, mixed> */
    public function createAntecedent(int $consultationId, CreateAntecedentInput $input): array
    {
        $consultation = $this->getById($consultationId);
        $this->assertConsultationAccessible($consultation);
        $this->assertConsultationWritable($consultation, 'Impossible d\'ajouter un antécédent sur une consultation clôturée.');

        $dpi = $this->resolveDpiFromConsultation($consultation);
        $antecedent = $this->antecedentService->createForDpi($dpi, $input);

        return $this->antecedentService->serializeSummary($antecedent);
    }

    public function deleteAntecedent(int $consultationId, int $antecedentId): void
    {
        $consultation = $this->getById($consultationId);
        $this->assertConsultationAccessible($consultation);
        $this->assertConsultationWritable($consultation, 'Impossible de supprimer un antécédent sur une consultation clôturée.');

        $dpi = $this->resolveDpiFromConsultation($consultation);
        $this->antecedentService->deleteForDpi($dpi, $antecedentId);
    }

    public function delete(int $id): void
    {
        $consultation = $this->getById($id);
        $this->assertConsultationAccessible($consultation);
        $this->assertDeletable($consultation);

        $this->entityManager->remove($consultation);
        $this->entityManager->flush();
    }

    public function getById(int $id): Consultation
    {
        $consultation = $this->consultationRepository->find($id);
        if (null === $consultation) {
            throw new NotFoundException('Consultation non trouvée.');
        }

        return $consultation;
    }

    public function assertConsultationReadable(int $consultationId): Consultation
    {
        $consultation = $this->getById($consultationId);
        $this->assertConsultationAccessible($consultation);

        return $consultation;
    }

    /** @return array<string, mixed> */
    public function serializeSummary(Consultation $consultation): array
    {
        $visite = $consultation->getVisite();
        $dpi = $visite?->getDpi();
        $patient = $dpi?->getPatient();
        $service = $visite?->getService();
        $openedBy = $consultation->getOpenedBy();

        return [
            'id' => $consultation->getId(),
            'statut' => $consultation->getStatut(),
            'typeConsultation' => $consultation->getTypeConsultation(),
            'motif' => $consultation->getMotif(),
            'consultedAt' => $consultation->getConsultedAt()?->format(\DateTimeInterface::ATOM),
            'debutAt' => $consultation->getDebutAt()?->format(\DateTimeInterface::ATOM),
            'finAt' => $consultation->getFinAt()?->format(\DateTimeInterface::ATOM),
            'allowedTransitions' => Consultation::getAllowedTransitions((string) $consultation->getStatut()),
            'visiteId' => $visite?->getId(),
            'visiteStatut' => $visite?->getStatut(),
            'alreadyHospitalized' => Visite::STATUT_HOSPITALISE === $visite?->getStatut(),
            'patientId' => $patient?->getId()?->toRfc4122(),
            'patientName' => $patient?->getFullName(),
            'patientStatus' => $patient?->getStatus(),
            'dpiStatut' => $dpi?->getStatut(),
            'recordWritable' => $this->isRecordWritable($visite),
            'numDossier' => $dpi?->getNumDossier(),
            'service' => null !== $service ? [
                'id' => $service->getId(),
                'libelle' => $service->getLibelle(),
            ] : null,
            'openedBy' => null !== $openedBy ? [
                'id' => (string) $openedBy->getId(),
                'fullName' => trim(sprintf(
                    '%s %s %s',
                    $openedBy->getPrenom() ?? '',
                    $openedBy->getNom() ?? '',
                    $openedBy->getPostNom() ?? '',
                )),
            ] : null,
            'diagnosticCount' => $consultation->getDiagnostics()->count(),
        ];
    }

    /** @return array<string, mixed> */
    public function serializeDetail(Consultation $consultation): array
    {
        $visite = $consultation->getVisite();
        $dpi = $visite?->getDpi();
        $patient = $dpi?->getPatient();
        $closedBy = $consultation->getClosedBy();

        return array_merge($this->serializeSummary($consultation), [
            'histoireMaladie' => $consultation->getHistoireMaladie(),
            'physicalExamText' => $consultation->getPhysicalExamText(),
            'consultationObservation' => $consultation->getPhysicalExamText(),
            'conduireATenir' => $consultation->getConduireATenir(),
            'followUpPlan' => $consultation->getConduireATenir(),
            'physicalExam' => $consultation->getPhysicalExam() ?? $this->physicalExamNormalizer->createEmpty(),
            'complementAnamnese' => $consultation->getComplementAnamnese() ?? [],
            'evolutionSheet' => $this->evolutionSheetNormalizer->normalize($consultation->getEvolutionSheet()),
            'hospitalizedAt' => $visite?->getHospitalizedAt()?->format(\DateTimeInterface::ATOM),
            'previousTourAt' => $this->resolvePreviousTourAt($consultation),
            'hasPhysicalExamData' => $this->physicalExamNormalizer->hasData($consultation->getPhysicalExam() ?? []),
            'isEditable' => $consultation->isEditable() && $this->isRecordWritable($visite),
            'isClosed' => $consultation->isClosed(),
            'patientSexe' => $patient?->getSexe(),
            'closeDisposition' => [
                'needsHospitalization' => $consultation->getNeedsHospitalization(),
                'wantsAppointment' => $consultation->getWantsAppointment(),
                'nextAppointmentAt' => $consultation->getNextAppointmentAt()?->format(\DateTimeInterface::ATOM),
                'hospitalizationPatientOpinion' => $consultation->getHospitalizationPatientOpinion(),
                'hospitalizationObservation' => $consultation->getHospitalizationObservation(),
                'closedBy' => null !== $closedBy ? [
                    'id' => (string) $closedBy->getId(),
                    'fullName' => trim(sprintf(
                        '%s %s %s',
                        $closedBy->getPrenom() ?? '',
                        $closedBy->getNom() ?? '',
                        $closedBy->getPostNom() ?? '',
                    )),
                ] : null,
            ],
            'triage' => null !== $visite?->getTriage() ? [
                'typeEntree' => $visite->getTriage()?->getTypeEntree(),
                'motif' => $visite->getTriage()?->getMotif(),
                'priorite' => $visite->getTriage()?->getPriorite(),
            ] : null,
        ]);
    }

    /** @return array<string, mixed> */
    public function buildMeta(): array
    {
        return [
            'statuts' => Consultation::getStatuts(),
            'creatableStatuts' => Consultation::getCreatableStatuts(),
            'types' => Consultation::getTypes(),
            'creatableTypes' => Consultation::getCreatableTypes(),
            'hospitalizationOpinions' => Consultation::getHospitalizationOpinions(),
            'physicalExamSchema' => $this->physicalExamNormalizer->createEmpty(),
        ];
    }

    private function applyVisiteOrientationAfterClose(Consultation $consultation, bool $dischargePatient = false): void
    {
        $visite = $consultation->getVisite();
        if (null === $visite) {
            return;
        }

        if (Visite::STATUT_HOSPITALISE === $visite->getStatut()) {
            if ($dischargePatient && Visite::canTransition((string) $visite->getStatut(), Visite::STATUT_TERMINEE)) {
                if ($consultation->getWantsAppointment() && null !== $consultation->getNextAppointmentAt()) {
                    $visite->setSortedPrevuAt($consultation->getNextAppointmentAt());
                }
                $visite->setSortedAt(new \DateTimeImmutable());
                $visite->setStatut(Visite::STATUT_TERMINEE);
            }

            return;
        }

        if ($consultation->getNeedsHospitalization()) {
            return;
        }

        if ($consultation->getWantsAppointment()) {
            $appointmentAt = $consultation->getNextAppointmentAt();
            if (null !== $appointmentAt) {
                $visite->setSortedPrevuAt($appointmentAt);
            }

            return;
        }

        if (Visite::canTransition((string) $visite->getStatut(), Visite::STATUT_TERMINEE)) {
            $visite->setSortedAt(new \DateTimeImmutable());
            $visite->setLit(null);
            $visite->setStatut(Visite::STATUT_TERMINEE);
        }
    }

    /**
     * @param list<string> $items
     *
     * @return list<string>
     */
    private function normalizeAnamneseList(array $items): array
    {
        $normalized = [];
        foreach ($items as $item) {
            $value = trim((string) $item);
            if ('' !== $value) {
                $normalized[] = $value;
            }
        }

        return array_values($normalized);
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

    private function resolvePreviousTourAt(Consultation $consultation): ?string
    {
        $visite = $consultation->getVisite();
        $consultedAt = $consultation->getConsultedAt();
        if (null === $visite || null === $consultedAt || Consultation::TYPE_AU_LIT !== $consultation->getTypeConsultation()) {
            return null;
        }

        $previous = $this->consultationRepository->findPreviousWardRound(
            (int) $visite->getId(),
            (int) $consultation->getId(),
            $consultedAt,
        );

        return $previous?->getConsultedAt()?->format(\DateTimeInterface::ATOM);
    }

    /** @return list<array<string, mixed>> */
    private function serializeTriageMesures(?Triage $triage): array
    {
        if (null === $triage) {
            return [];
        }

        return array_map(
            static fn (TriageMesure $mesure): array => [
                'id' => $mesure->getId(),
                'source' => VisiteMesure::SOURCE_TRIAGE,
                'signeVitalId' => $mesure->getSigneVital()?->getId(),
                'code' => $mesure->getSigneVital()?->getCode(),
                'libelle' => $mesure->getSigneVital()?->getLibelle(),
                'unite' => $mesure->getSigneVital()?->getUnite(),
                'valeur' => $mesure->getValeur(),
                'measuredAt' => $triage->getTriagedAt()?->format(\DateTimeInterface::ATOM),
            ],
            $triage->getMesures()->toArray(),
        );
    }

    /** @return array<string, mixed> */
    private function serializeVisiteMesure(VisiteMesure $mesure): array
    {
        $personnel = $mesure->getMeasuredBy();

        return [
            'id' => $mesure->getId(),
            'source' => $mesure->getSource(),
            'signeVitalId' => $mesure->getSigneVital()?->getId(),
            'code' => $mesure->getSigneVital()?->getCode(),
            'libelle' => $mesure->getSigneVital()?->getLibelle(),
            'unite' => $mesure->getSigneVital()?->getUnite(),
            'valeur' => $mesure->getValeur(),
            'measuredAt' => $mesure->getMeasuredAt()?->format(\DateTimeInterface::ATOM),
            'measuredBy' => null !== $personnel ? [
                'id' => (string) $personnel->getId(),
                'fullName' => trim(sprintf(
                    '%s %s %s',
                    $personnel->getPrenom() ?? '',
                    $personnel->getNom() ?? '',
                    $personnel->getPostNom() ?? '',
                )),
            ] : null,
        ];
    }

    private function applyStatutTransition(Consultation $consultation, string $targetStatut): void
    {
        $currentStatut = (string) $consultation->getStatut();

        if (!Consultation::canTransition($currentStatut, $targetStatut)) {
            throw new ConflictException(sprintf(
                'Transition de statut impossible : %s → %s.',
                $currentStatut,
                $targetStatut,
            ));
        }

        $now = new \DateTimeImmutable();

        if (Consultation::STATUT_EN_COURS === $targetStatut && null === $consultation->getDebutAt()) {
            $consultation->setDebutAt($now);
        }

        if (in_array($targetStatut, [Consultation::STATUT_TERMINEE, Consultation::STATUT_ANNULEE], true)) {
            $consultation->setFinAt($now);
        }

        $consultation->setStatut($targetStatut);
    }

    private function assertDeletable(Consultation $consultation): void
    {
        if (!$consultation->getDiagnostics()->isEmpty()) {
            throw new ConflictException('Impossible de supprimer une consultation avec des diagnostics.');
        }

        if (Consultation::STATUT_TERMINEE === $consultation->getStatut()) {
            throw new ConflictException('Impossible de supprimer une consultation terminée.');
        }
    }

    public function assertConsultationWritable(
        Consultation $consultation,
        string $closedMessage = 'Une consultation terminée ou annulée ne peut plus être modifiée.',
    ): void {
        if (!Consultation::isEditableStatut((string) $consultation->getStatut())) {
            throw new ConflictException($closedMessage);
        }

        $this->assertRecordWritable($consultation->getVisite());
    }

    public function assertRecordWritable(?Visite $visite): void
    {
        if (null === $visite) {
            throw new ConflictException('Visite introuvable.');
        }

        $dpi = $visite->getDpi();
        $patient = $dpi?->getPatient();

        if (null !== $patient && !$patient->isClinicallyWritable()) {
            if ($patient->isDeceased()) {
                throw new ConflictException('Ce patient est décédé. Le dossier clinique est en lecture seule.');
            }

            throw new ConflictException('Ce patient est inactif. Réactivez-le pour modifier le dossier clinique.');
        }

        if (null !== $dpi && !$dpi->isWritable()) {
            throw new ConflictException('Ce dossier patient n\'est plus ouvert. Lecture seule.');
        }
    }

    public function isRecordWritable(?Visite $visite): bool
    {
        if (null === $visite) {
            return false;
        }

        $dpi = $visite->getDpi();
        $patient = $dpi?->getPatient();

        if (null !== $patient && !$patient->isClinicallyWritable()) {
            return false;
        }

        if (null !== $dpi && !$dpi->isWritable()) {
            return false;
        }

        return true;
    }

    private function assertVisiteAllowsConsultation(Visite $visite): void
    {
        $statut = (string) $visite->getStatut();
        if (!in_array($statut, [Visite::STATUT_EN_COURS, Visite::STATUT_HOSPITALISE], true)) {
            throw new ConflictException('La visite doit être en cours ou hospitalisée pour ouvrir une consultation.');
        }
    }

    private function assertTypeMatchesVisite(?Visite $visite, string $type): void
    {
        if (null === $visite) {
            throw new ConflictException('Visite introuvable.');
        }

        if (Visite::STATUT_HOSPITALISE !== $visite->getStatut() && Consultation::TYPE_AU_LIT === $type) {
            throw new ConflictException('Une consultation au lit n\'est possible que pour un patient déjà hospitalisé.');
        }
    }

    private function resolveDefaultType(Visite $visite): string
    {
        return Visite::STATUT_HOSPITALISE === $visite->getStatut()
            ? Consultation::TYPE_AU_LIT
            : Consultation::TYPE_NORMALE;
    }

    private function resolveInitialConsultationStatut(Visite $visite): string
    {
        return match ((string) $visite->getStatut()) {
            Visite::STATUT_ANNULEE => Consultation::STATUT_ANNULEE,
            Visite::STATUT_TERMINEE => Consultation::STATUT_TERMINEE,
            Visite::STATUT_PLANIFIEE => Consultation::STATUT_PLANIFIEE,
            default => Consultation::STATUT_EN_COURS,
        };
    }

    private function getVisiteById(int $id): Visite
    {
        $visite = $this->visiteRepository->find($id);
        if (null === $visite) {
            throw new NotFoundException('Visite non trouvée.');
        }

        return $visite;
    }

    private function assertVisiteAccessible(Visite $visite): void
    {
        $service = $visite->getService();
        if (null === $service) {
            return;
        }

        $accessScope = $this->resolveAccessScope();
        if (!$accessScope->allowsService($service)) {
            throw new ConflictException('Vous n\'avez pas accès à ce service.');
        }
    }

    private function assertConsultationAccessible(Consultation $consultation): void
    {
        $visite = $consultation->getVisite();
        if (null !== $visite) {
            $this->assertVisiteAccessible($visite);
        }
    }

    private function resolveDpiFromConsultation(Consultation $consultation): Dpi
    {
        $visite = $consultation->getVisite();
        if (null === $visite) {
            throw new NotFoundException('Visite introuvable pour cette consultation.');
        }

        $dpi = $visite->getDpi();
        if (null === $dpi) {
            throw new NotFoundException('Dossier patient (DPI) introuvable.');
        }

        return $dpi;
    }

    private function resolveAccessScope(): PersonnelAccessScope
    {
        $viewer = $this->security->getUser();
        if (!$viewer instanceof Personnel) {
            return new PersonnelAccessScope(unrestricted: true);
        }

        return $this->permissionChecker->resolveAccessScope($viewer, CliniquePermissions::CONSULTATION_READ);
    }

    private function resolveCurrentPersonnel(): ?Personnel
    {
        $viewer = $this->security->getUser();

        return $viewer instanceof Personnel ? $viewer : null;
    }

    private function normalizeOptionalText(?string $value): ?string
    {
        if (null === $value) {
            return null;
        }

        $trimmed = trim($value);

        return '' === $trimmed ? null : $trimmed;
    }
}
