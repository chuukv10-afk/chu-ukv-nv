<?php

namespace App\Service\Clinique;

use App\DTO\Clinique\CreateDemandeExamenInput;
use App\DTO\Clinique\CreateDiagnosticInput;
use App\DTO\Clinique\DemandeExamenListQuery;
use App\DTO\Clinique\SaisieResultatInput;
use App\DTO\Common\PaginatedResult;
use App\Entity\Consultation;
use App\Entity\DemandeExamen;
use App\Entity\Personnel;
use App\Exception\ConflictException;
use App\Exception\NotFoundException;
use App\Repository\DemandeExamenRepository;
use App\Repository\ExamenRepository;
use App\Security\Permission\CliniquePermissions;
use App\Security\PermissionChecker;
use App\Security\PersonnelAccessScope;
use App\Service\Patient\PatientService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Validator\Exception\ValidationFailedException;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final class DemandeExamenService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly DemandeExamenRepository $demandeExamenRepository,
        private readonly ExamenRepository $examenRepository,
        private readonly ConsultationService $consultationService,
        private readonly DiagnosticService $diagnosticService,
        private readonly PatientService $patientService,
        private readonly ValidatorInterface $validator,
        private readonly PermissionChecker $permissionChecker,
        private readonly Security $security,
    ) {
    }

    public function paginateByConsultation(int $consultationId, DemandeExamenListQuery $query): PaginatedResult
    {
        $this->consultationService->assertConsultationReadable($consultationId);

        return $this->paginate(
            $this->demandeExamenRepository->paginateByConsultation($consultationId, $query->page, $query->limit),
            $query,
            false,
        );
    }

    public function paginateByPatientId(string $patientId, DemandeExamenListQuery $query): PaginatedResult
    {
        $this->patientService->getById($patientId);

        return $this->paginate(
            $this->demandeExamenRepository->paginateByPatientId($patientId, $query->page, $query->limit),
            $query,
            true,
        );
    }

    public function paginateGlobal(DemandeExamenListQuery $query): PaginatedResult
    {
        return $this->paginate(
            $this->demandeExamenRepository->paginateGlobal(
                $query->page,
                $query->limit,
                $query->search,
                $query->statut,
                $query->typeExamenId,
                $this->resolveAccessScope(),
            ),
            $query,
            true,
        );
    }

    /** @return array<string, mixed> */
    public function createForConsultation(int $consultationId, CreateDemandeExamenInput $input): array
    {
        $errors = $this->validator->validate($input);
        if (count($errors) > 0) {
            throw new ValidationFailedException($input, $errors);
        }

        $consultation = $this->consultationService->assertConsultationReadable($consultationId);
        $this->assertConsultationWritable($consultation);

        $examen = $this->examenRepository->find((int) $input->examenId);
        if (null === $examen) {
            throw new NotFoundException('Examen non trouvé.');
        }

        $demande = (new DemandeExamen())
            ->setConsultation($consultation)
            ->setExamen($examen)
            ->setPrescripteur($this->resolveCurrentPersonnel())
            ->setNoteMedecin($this->normalizeOptionalText($input->noteMedecin, 255))
            ->setStatut(DemandeExamen::STATUT_DEMANDE)
            ->setDemandeAt(new \DateTimeImmutable());

        $this->entityManager->persist($demande);
        $this->entityManager->flush();

        return $this->serializeSummary($demande, false);
    }

    /** @return array<string, mixed> */
    public function prendreEnCharge(int $demandeId): array
    {
        return $this->transition($demandeId, DemandeExamen::STATUT_EN_COURS);
    }

    /** @return array<string, mixed> */
    public function saisirResultat(int $demandeId, SaisieResultatInput $input): array
    {
        $errors = $this->validator->validate($input);
        if (count($errors) > 0) {
            throw new ValidationFailedException($input, $errors);
        }

        $demande = $this->getAccessibleDemande($demandeId);
        if (!DemandeExamen::canTransition((string) $demande->getStatut(), DemandeExamen::STATUT_RESULTAT_DISPONIBLE)) {
            throw new ConflictException('Impossible de saisir un résultat pour cette demande.');
        }

        $demande
            ->setResultat($this->normalizeRequiredText($input->resultat, DemandeExamen::RESULTAT_MAX_LENGTH))
            ->setFichier($this->normalizeOptionalText($input->fichier, 255))
            ->setStatut(DemandeExamen::STATUT_RESULTAT_DISPONIBLE);

        $this->entityManager->flush();

        return $this->serializeSummary($demande, true);
    }

    /** @return array<string, mixed> */
    public function valider(int $demandeId): array
    {
        return $this->transition($demandeId, DemandeExamen::STATUT_VALIDE);
    }

    /** @return array<string, mixed> */
    public function annuler(int $demandeId): array
    {
        $demande = $this->getAccessibleDemande($demandeId);
        $this->assertConsultationWritable($demande->getConsultation());

        if (!DemandeExamen::canCancel((string) $demande->getStatut())) {
            throw new ConflictException('Cette demande ne peut plus être annulée.');
        }

        $demande->setStatut(DemandeExamen::STATUT_ANNULEE);
        $this->entityManager->flush();

        return $this->serializeSummary($demande, true);
    }

    /** @return array<string, mixed> */
    public function createLinkedDiagnostic(int $demandeId, CreateDiagnosticInput $input): array
    {
        $demande = $this->getAccessibleDemande($demandeId);
        $consultation = $demande->getConsultation();
        if (null === $consultation) {
            throw new NotFoundException('Consultation introuvable pour cette demande.');
        }

        return $this->diagnosticService->createForConsultation(
            (int) $consultation->getId(),
            $input,
            $demande,
        );
    }

    /** @return array<string, mixed> */
    public function buildMeta(): array
    {
        return [
            'statuts' => DemandeExamen::getStatuts(),
            'resultatMaxLength' => DemandeExamen::RESULTAT_MAX_LENGTH,
        ];
    }

    /**
     * @param array{items: list<DemandeExamen>, total: int} $result
     */
    private function paginate(array $result, DemandeExamenListQuery $query, bool $withContext): PaginatedResult
    {
        $errors = $this->validator->validate($query);
        if (count($errors) > 0) {
            throw new ValidationFailedException($query, $errors);
        }

        return new PaginatedResult(
            array_map(
                fn (DemandeExamen $demande): array => $this->serializeSummary($demande, $withContext),
                $result['items'],
            ),
            $query->page,
            $query->limit,
            $result['total'],
        );
    }

    /** @return array<string, mixed> */
    private function transition(int $demandeId, string $toStatut): array
    {
        $demande = $this->getAccessibleDemande($demandeId);
        if (!DemandeExamen::canTransition((string) $demande->getStatut(), $toStatut)) {
            throw new ConflictException(sprintf(
                'Transition %s → %s non autorisée.',
                $demande->getStatut(),
                $toStatut,
            ));
        }

        $demande->setStatut($toStatut);
        $this->entityManager->flush();

        return $this->serializeSummary($demande, true);
    }

    private function getAccessibleDemande(int $demandeId): DemandeExamen
    {
        $demande = $this->demandeExamenRepository->find($demandeId);
        if (null === $demande) {
            throw new NotFoundException('Demande d\'examen non trouvée.');
        }

        $consultation = $demande->getConsultation();
        if (null !== $consultation) {
            $this->consultationService->assertConsultationReadable((int) $consultation->getId());
        }

        return $demande;
    }

    private function assertConsultationWritable(?Consultation $consultation): void
    {
        if (null === $consultation) {
            throw new ConflictException('Impossible de modifier les demandes d\'une consultation clôturée.');
        }

        $this->consultationService->assertConsultationWritable(
            $consultation,
            'Impossible de modifier les demandes d\'une consultation clôturée.',
        );
    }

    /** @return array<string, mixed> */
    public function serializeSummary(DemandeExamen $demande, bool $withContext = false): array
    {
        $examen = $demande->getExamen();
        $typeExamen = $examen?->getTypeExamen();
        $prescripteur = $demande->getPrescripteur();
        $diagnostics = [];
        foreach ($demande->getDiagnostics() as $diagnostic) {
            $maladie = $diagnostic->getMaladie();
            $diagnostics[] = [
                'id' => $diagnostic->getId(),
                'type' => $diagnostic->getType(),
                'certitude' => $diagnostic->getCertitude(),
                'createdAt' => $diagnostic->getCreatedAt()?->format(\DateTimeInterface::ATOM),
                'maladie' => null === $maladie ? null : [
                    'id' => $maladie->getId(),
                    'codeCim10' => $maladie->getCodeCim10(),
                    'libelle' => $maladie->getLibelle(),
                ],
            ];
        }

        $payload = [
            'id' => $demande->getId(),
            'statut' => $demande->getStatut(),
            'demandeAt' => $demande->getDemandeAt()?->format(\DateTimeInterface::ATOM),
            'noteMedecin' => $demande->getNoteMedecin(),
            'resultat' => $demande->getResultat(),
            'fichier' => $demande->getFichier(),
            'allowedTransitions' => DemandeExamen::getAllowedTransitions((string) $demande->getStatut()),
            'canCancel' => DemandeExamen::canCancel((string) $demande->getStatut()),
            'examen' => null === $examen ? null : [
                'id' => $examen->getId(),
                'code' => $examen->getCode(),
                'libelle' => $examen->getLibelle(),
                'typeExamen' => null === $typeExamen ? null : [
                    'id' => $typeExamen->getId(),
                    'code' => $typeExamen->getCode(),
                    'libelle' => $typeExamen->getLibelle(),
                ],
            ],
            'prescripteur' => null === $prescripteur ? null : [
                'id' => (string) $prescripteur->getId(),
                'fullName' => $this->formatPersonnelName($prescripteur),
            ],
            'diagnostics' => $diagnostics,
        ];

        if ($withContext) {
            $consultation = $demande->getConsultation();
            $visite = $consultation?->getVisite();
            $dpi = $visite?->getDpi();
            $patient = $dpi?->getPatient();
            $service = $visite?->getService();

            $payload['consultation'] = null === $consultation ? null : [
                'id' => $consultation->getId(),
                'consultedAt' => $consultation->getConsultedAt()?->format(\DateTimeInterface::ATOM),
                'statut' => $consultation->getStatut(),
                'isEditable' => Consultation::isEditableStatut((string) $consultation->getStatut()),
                'service' => null === $service ? null : [
                    'id' => $service->getId(),
                    'libelle' => $service->getLibelle(),
                ],
            ];
            $payload['patient'] = null === $patient ? null : [
                'id' => $patient->getId()?->toRfc4122(),
                'fullName' => $patient->getFullName(),
                'numDossier' => $dpi?->getNumDossier(),
            ];
        }

        return $payload;
    }

    private function resolveAccessScope(): PersonnelAccessScope
    {
        $viewer = $this->security->getUser();
        if (!$viewer instanceof Personnel) {
            return new PersonnelAccessScope(unrestricted: true);
        }

        return $this->permissionChecker->resolveAccessScope($viewer, CliniquePermissions::DEMANDE_EXAMEN_READ);
    }

    private function resolveCurrentPersonnel(): ?Personnel
    {
        $viewer = $this->security->getUser();

        return $viewer instanceof Personnel ? $viewer : null;
    }

    private function formatPersonnelName(Personnel $personnel): string
    {
        return trim(sprintf(
            '%s %s %s',
            $personnel->getPrenom() ?? '',
            $personnel->getNom() ?? '',
            $personnel->getPostNom() ?? '',
        ));
    }

    private function normalizeOptionalText(?string $value, int $maxLength): ?string
    {
        if (null === $value) {
            return null;
        }

        $trimmed = trim($value);
        if ('' === $trimmed) {
            return null;
        }

        return mb_substr($trimmed, 0, $maxLength);
    }

    private function normalizeRequiredText(?string $value, int $maxLength): string
    {
        $normalized = $this->normalizeOptionalText($value, $maxLength);
        if (null === $normalized) {
            throw new ConflictException('Le résultat est obligatoire.');
        }

        return $normalized;
    }
}
