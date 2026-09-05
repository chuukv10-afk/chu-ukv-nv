<?php

namespace App\Service\Clinique;

use App\DTO\Clinique\CreateDiagnosticInput;
use App\DTO\Clinique\DiagnosticListQuery;
use App\DTO\Common\PaginatedResult;
use App\Entity\DemandeExamen;
use App\Entity\Diagnostic;
use App\Exception\ConflictException;
use App\Exception\NotFoundException;
use App\Repository\DiagnosticRepository;
use App\Repository\MaladieRepository;
use App\Service\Patient\PatientService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Validator\Exception\ValidationFailedException;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final class DiagnosticService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly DiagnosticRepository $diagnosticRepository,
        private readonly MaladieRepository $maladieRepository,
        private readonly ConsultationService $consultationService,
        private readonly PatientService $patientService,
        private readonly ValidatorInterface $validator,
    ) {
    }

    public function paginateByConsultation(int $consultationId, DiagnosticListQuery $query): PaginatedResult
    {
        $consultation = $this->consultationService->getById($consultationId);
        $this->consultationService->assertConsultationReadable($consultationId);

        if ($query->isVisiteScope()) {
            $visite = $consultation->getVisite();
            if (null === $visite) {
                throw new NotFoundException('Visite de la consultation introuvable.');
            }

            return $this->paginate(
                $this->diagnosticRepository->paginateByVisiteId((int) $visite->getId(), $query->page, $query->limit),
                $query,
                true,
                $consultationId,
            );
        }

        return $this->paginate(
            $this->diagnosticRepository->paginateByConsultation($consultationId, $query->page, $query->limit),
            $query,
            false,
            $consultationId,
        );
    }

    public function paginateByPatientId(string $patientId, DiagnosticListQuery $query): PaginatedResult
    {
        $this->patientService->getById($patientId);

        return $this->paginate(
            $this->diagnosticRepository->paginateByPatientId($patientId, $query->page, $query->limit),
            $query,
            true,
            null,
        );
    }

    /** @return array<string, mixed> */
    public function createForConsultation(
        int $consultationId,
        CreateDiagnosticInput $input,
        ?DemandeExamen $demandeExamen = null,
    ): array {
        $errors = $this->validator->validate($input);
        if (count($errors) > 0) {
            throw new ValidationFailedException($input, $errors);
        }

        $consultation = $this->consultationService->getById($consultationId);
        $this->consultationService->assertConsultationReadable($consultationId);
        $this->consultationService->assertConsultationWritable(
            $consultation,
            'Impossible d\'ajouter un diagnostic sur une consultation clôturée.',
        );

        $type = Diagnostic::normalizeType($input->type);
        if (!Diagnostic::isValidType($type)) {
            throw new ConflictException('Type de diagnostic invalide.');
        }

        $certitude = Diagnostic::normalizeCertitude($input->certitude);
        if (!Diagnostic::isValidCertitude($certitude)) {
            throw new ConflictException('Certitude de diagnostic invalide.');
        }

        $maladie = $this->maladieRepository->find((int) $input->maladieId);
        if (null === $maladie) {
            throw new NotFoundException('Maladie non trouvée.');
        }

        if (null !== $this->diagnosticRepository->findDuplicate($consultationId, (int) $maladie->getId())) {
            throw new ConflictException('Ce diagnostic est déjà enregistré pour cette consultation.');
        }

        if (null !== $demandeExamen && (int) $demandeExamen->getConsultation()?->getId() !== $consultationId) {
            throw new ConflictException('Cette demande d\'examen n\'appartient pas à la consultation.');
        }

        $diagnostic = (new Diagnostic())
            ->setConsultation($consultation)
            ->setMaladie($maladie)
            ->setType($type)
            ->setCertitude($certitude)
            ->setStadeEvolution(Diagnostic::STADE_NON_RENSEIGNE)
            ->setRemarque($this->normalizeRemarque($input->remarque))
            ->setDemandeExamen($demandeExamen)
            ->setCreatedAt(new \DateTimeImmutable());

        $this->entityManager->persist($diagnostic);
        $this->entityManager->flush();

        return $this->serializeSummary($diagnostic, false);
    }

    public function deleteForConsultation(int $consultationId, int $diagnosticId): void
    {
        $consultation = $this->consultationService->getById($consultationId);
        $this->consultationService->assertConsultationReadable($consultationId);
        $this->consultationService->assertConsultationWritable(
            $consultation,
            'Impossible de supprimer un diagnostic sur une consultation clôturée.',
        );

        $diagnostic = $this->diagnosticRepository->find($diagnosticId);
        if (null === $diagnostic || (int) $diagnostic->getConsultation()?->getId() !== $consultationId) {
            throw new NotFoundException('Diagnostic non trouvé.');
        }

        $this->entityManager->remove($diagnostic);
        $this->entityManager->flush();
    }

    /** @return array<string, mixed> */
    public function buildMeta(): array
    {
        return [
            'types' => Diagnostic::getTypes(),
            'certitudes' => Diagnostic::getCertitudes(),
        ];
    }

    /**
     * @param array{items: list<Diagnostic>, total: int} $result
     */
    private function paginate(
        array $result,
        DiagnosticListQuery $query,
        bool $withConsultationContext,
        ?int $currentConsultationId = null,
    ): PaginatedResult {
        $errors = $this->validator->validate($query);
        if (count($errors) > 0) {
            throw new ValidationFailedException($query, $errors);
        }

        return new PaginatedResult(
            array_map(
                fn (Diagnostic $diagnostic): array => $this->serializeSummary(
                    $diagnostic,
                    $withConsultationContext,
                    $currentConsultationId,
                ),
                $result['items'],
            ),
            $query->page,
            $query->limit,
            $result['total'],
        );
    }

    /** @return array<string, mixed> */
    public function serializeSummary(
        Diagnostic $diagnostic,
        bool $withConsultationContext = false,
        ?int $currentConsultationId = null,
    ): array {
        $maladie = $diagnostic->getMaladie();
        $consultation = $diagnostic->getConsultation();
        $payload = [
            'id' => $diagnostic->getId(),
            'type' => $diagnostic->getType(),
            'certitude' => $diagnostic->getCertitude(),
            'stadeEvolution' => $diagnostic->getStadeEvolution(),
            'remarque' => $diagnostic->getRemarque(),
            'fromCurrentConsultation' => null !== $currentConsultationId
                && (int) $consultation?->getId() === $currentConsultationId,
            'maladie' => null === $maladie ? null : [
                'id' => $maladie->getId(),
                'codeCim10' => $maladie->getCodeCim10(),
                'libelle' => $maladie->getLibelle(),
            ],
            'createdAt' => $diagnostic->getCreatedAt()?->format(\DateTimeInterface::ATOM),
            'demandeExamen' => $this->serializeDemandeExamen($diagnostic->getDemandeExamen()),
        ];

        if ($withConsultationContext) {
            $visite = $consultation?->getVisite();
            $service = $visite?->getService();

            $payload['consultation'] = null === $consultation ? null : [
                'id' => $consultation->getId(),
                'consultedAt' => $consultation->getConsultedAt()?->format(\DateTimeInterface::ATOM),
                'statut' => $consultation->getStatut(),
                'typeConsultation' => $consultation->getTypeConsultation(),
                'service' => null === $service ? null : [
                    'id' => $service->getId(),
                    'libelle' => $service->getLibelle(),
                ],
            ];
        }

        return $payload;
    }

    /** @return array<string, mixed>|null */
    private function serializeDemandeExamen(?DemandeExamen $demande): ?array
    {
        if (null === $demande) {
            return null;
        }

        $examen = $demande->getExamen();

        return [
            'id' => $demande->getId(),
            'statut' => $demande->getStatut(),
            'examen' => null === $examen ? null : [
                'id' => $examen->getId(),
                'code' => $examen->getCode(),
                'libelle' => $examen->getLibelle(),
            ],
        ];
    }

    private function normalizeRemarque(?string $remarque): ?string
    {
        if (null === $remarque) {
            return null;
        }

        $trimmed = trim($remarque);

        return '' === $trimmed ? null : mb_substr($trimmed, 0, 255);
    }
}
