<?php

namespace App\Service\Patient;

use App\DTO\Common\PaginatedResult;
use App\DTO\Patient\CreatePatientInput;
use App\DTO\Patient\PatientListQuery;
use App\DTO\Patient\UpdateDpiInput;
use App\DTO\Patient\UpdatePatientInput;
use App\Entity\Dpi;
use App\Entity\Patient;
use App\Exception\ConflictException;
use App\Exception\NotFoundException;
use App\Repository\DpiRepository;
use App\Repository\PatientRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Exception\ValidationFailedException;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final class PatientService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly PatientRepository $patientRepository,
        private readonly DpiRepository $dpiRepository,
        private readonly ValidatorInterface $validator,
    ) {
    }

    public function paginate(PatientListQuery $query): PaginatedResult
    {
        $errors = $this->validator->validate($query);
        if (count($errors) > 0) {
            throw new ValidationFailedException($query, $errors);
        }

        $result = $this->patientRepository->paginate(
            $query->page,
            $query->limit,
            $query->search,
            $query->status,
            $query->sexe,
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
    public function buildExportRows(PatientListQuery $query): array
    {
        $errors = $this->validator->validate($query);
        if (count($errors) > 0) {
            throw new ValidationFailedException($query, $errors);
        }

        $items = $this->patientRepository->findForExport(
            $query->search,
            $query->status,
            $query->sexe,
        );

        return array_map(
            fn (Patient $patient): array => $this->buildExportRow($patient),
            $items,
        );
    }

    /** @return list<string|null> */
    public function buildExportRow(Patient $patient): array
    {
        $dpi = $patient->getDpi();

        return [
            $dpi?->getNumDossier(),
            $patient->getNom(),
            $patient->getPostNom(),
            $patient->getPrenom(),
            $patient->getDateNaissance()?->format('d/m/Y'),
            $patient->getSexe(),
            $patient->getTelephone(),
            $patient->getStatus(),
            $dpi?->getStatut(),
            $patient->getGroupeSanguin(),
            $patient->getAdresse(),
            $patient->getPersonneAprevenir(),
            $patient->getContactAPrevenir(),
        ];
    }

    public function create(CreatePatientInput $input): Patient
    {
        $errors = $this->validator->validate($input);
        if (count($errors) > 0) {
            throw new ValidationFailedException($input, $errors);
        }

        $dateNaissance = $this->parseDateNaissance($input->dateNaissance);

        $patient = (new Patient())
            ->setNom(trim($input->nom))
            ->setPostNom(trim($input->postNom))
            ->setPrenom($this->normalizeOptionalText($input->prenom))
            ->setTelephone($this->normalizeOptionalText($input->telephone))
            ->setAdresse($this->normalizeOptionalText($input->adresse))
            ->setLieuNaissance($this->normalizeOptionalText($input->lieuNaissance))
            ->setDateNaissance($dateNaissance)
            ->setSexe(strtoupper(trim($input->sexe)))
            ->setGroupeSanguin($this->normalizeOptionalText($input->groupeSanguin))
            ->setPersonneAprevenir($this->normalizeOptionalText($input->personneAprevenir))
            ->setContactAPrevenir($this->normalizeOptionalText($input->contactAPrevenir))
            ->setStatus(Patient::normalizeStatus($input->status));

        $dpi = (new Dpi())
            ->setNumDossier($this->generateNumDossier())
            ->setStatut(Dpi::STATUT_OUVERT)
            ->setPatient($patient);

        $patient->setDpi($dpi);

        $this->entityManager->persist($patient);
        $this->entityManager->persist($dpi);
        $this->entityManager->flush();

        return $patient;
    }

    public function update(string $id, UpdatePatientInput $input): Patient
    {
        $errors = $this->validator->validate($input);
        if (count($errors) > 0) {
            throw new ValidationFailedException($input, $errors);
        }

        $patient = $this->getById($id);

        if ($patient->isDeceased()) {
            $newStatus = Patient::normalizeStatus($input->status);
            if ($newStatus !== Patient::STATUS_DECEDE) {
                $patient->setStatus($newStatus);
                $this->entityManager->flush();

                return $patient;
            }

            throw new ConflictException('Ce patient est décédé. Seul le changement de statut est autorisé.');
        }

        $dateNaissance = $this->parseDateNaissance($input->dateNaissance);

        $patient
            ->setNom(trim($input->nom))
            ->setPostNom(trim($input->postNom))
            ->setPrenom($this->normalizeOptionalText($input->prenom))
            ->setTelephone($this->normalizeOptionalText($input->telephone))
            ->setAdresse($this->normalizeOptionalText($input->adresse))
            ->setLieuNaissance($this->normalizeOptionalText($input->lieuNaissance))
            ->setDateNaissance($dateNaissance)
            ->setSexe(strtoupper(trim($input->sexe)))
            ->setGroupeSanguin($this->normalizeOptionalText($input->groupeSanguin))
            ->setPersonneAprevenir($this->normalizeOptionalText($input->personneAprevenir))
            ->setContactAPrevenir($this->normalizeOptionalText($input->contactAPrevenir))
            ->setStatus(Patient::normalizeStatus($input->status));

        if (Patient::STATUS_DECEDE === Patient::normalizeStatus($input->status)) {
            $dpi = $patient->getDpi();
            if (null !== $dpi && Dpi::STATUT_OUVERT === Dpi::normalizeStatut((string) $dpi->getStatut())) {
                $dpi->setStatut(Dpi::STATUT_ARCHIVE);
            }
        }

        $this->entityManager->flush();

        return $patient;
    }

    public function delete(string $id): void
    {
        $patient = $this->getById($id);
        $this->assertDeletable($patient);
        $this->entityManager->remove($patient);
        $this->entityManager->flush();
    }

    public function getById(string $id): Patient
    {
        if (!Uuid::isValid($id)) {
            throw new NotFoundException('Patient non trouvé.');
        }

        $patient = $this->patientRepository->find(Uuid::fromString($id));
        if (null === $patient) {
            throw new NotFoundException('Patient non trouvé.');
        }

        return $patient;
    }

    public function getDpiByPatientId(string $patientId): Dpi
    {
        $patient = $this->getById($patientId);
        $dpi = $patient->getDpi();
        if (null === $dpi) {
            throw new NotFoundException('Dossier patient (DPI) non trouvé.');
        }

        return $dpi;
    }

    public function updateDpi(string $patientId, UpdateDpiInput $input): Dpi
    {
        $errors = $this->validator->validate($input);
        if (count($errors) > 0) {
            throw new ValidationFailedException($input, $errors);
        }

        $dpi = $this->getDpiByPatientId($patientId);
        $dpi->setStatut(Dpi::normalizeStatut($input->statut));
        $this->entityManager->flush();

        return $dpi;
    }

    /** @return array<string, mixed> */
    public function serializeSummary(Patient $patient): array
    {
        $dpi = $patient->getDpi();

        return [
            'id' => (string) $patient->getId(),
            'nom' => $patient->getNom(),
            'postNom' => $patient->getPostNom(),
            'prenom' => $patient->getPrenom(),
            'fullName' => $patient->getFullName(),
            'telephone' => $patient->getTelephone(),
            'sexe' => $patient->getSexe(),
            'status' => $patient->getStatus(),
            'dateNaissance' => $patient->getDateNaissance()?->format('Y-m-d'),
            'numDossier' => $dpi?->getNumDossier(),
            'dpiStatut' => $dpi?->getStatut(),
            'visiteCount' => $dpi?->getVisites()->count() ?? 0,
            'antecedentCount' => $dpi?->getAntecedents()->count() ?? 0,
            'createdAt' => $patient->getCreatedAt()?->format(\DateTimeInterface::ATOM),
        ];
    }

    /** @return array<string, mixed> */
    public function serializeDetail(Patient $patient): array
    {
        $dpi = $patient->getDpi();

        return [
            ...$this->serializeSummary($patient),
            'adresse' => $patient->getAdresse(),
            'lieuNaissance' => $patient->getLieuNaissance(),
            'groupeSanguin' => $patient->getGroupeSanguin(),
            'personneAprevenir' => $patient->getPersonneAprevenir(),
            'contactAPrevenir' => $patient->getContactAPrevenir(),
            'updatedAt' => $patient->getUpdatedAt()?->format(\DateTimeInterface::ATOM),
            'dpi' => null !== $dpi ? $this->serializeDpi($dpi) : null,
        ];
    }

    /** @return array<string, mixed> */
    public function serializeDpi(Dpi $dpi): array
    {
        return [
            'id' => $dpi->getId(),
            'numDossier' => $dpi->getNumDossier(),
            'statut' => $dpi->getStatut(),
            'visiteCount' => $dpi->getVisites()->count(),
            'antecedentCount' => $dpi->getAntecedents()->count(),
            'createdAt' => $dpi->getCreatedAt()?->format(\DateTimeInterface::ATOM),
            'updatedAt' => $dpi->getUpdatedAt()?->format(\DateTimeInterface::ATOM),
        ];
    }

    /** @return array<string, mixed> */
    public function serializeDpiWithPatient(Dpi $dpi): array
    {
        $patient = $dpi->getPatient();
        if (null === $patient) {
            throw new NotFoundException('Patient non trouvé.');
        }

        return [
            'patient' => $this->serializeDetail($patient),
            'dpi' => $this->serializeDpi($dpi),
        ];
    }

    private function assertDeletable(Patient $patient): void
    {
        $dpi = $patient->getDpi();
        if (null !== $dpi && (!$dpi->getVisites()->isEmpty() || !$dpi->getAntecedents()->isEmpty())) {
            throw new ConflictException('Ce patient possède des visites ou antécédents et ne peut pas être supprimé.');
        }
    }

    private function generateNumDossier(): string
    {
        $year = (int) date('Y');
        $sequence = $this->dpiRepository->getNextSequenceForYear($year);

        return sprintf('DPI-%d-%05d', $year, $sequence);
    }

    private function parseDateNaissance(string $value): \DateTime
    {
        $date = \DateTimeImmutable::createFromFormat('Y-m-d', trim($value));
        if (false === $date) {
            throw new ConflictException('Date de naissance invalide.');
        }

        return \DateTime::createFromImmutable($date);
    }

    private function normalizeOptionalText(?string $value): ?string
    {
        if (null === $value) {
            return null;
        }

        $normalized = trim($value);

        return '' === $normalized ? null : $normalized;
    }
}
