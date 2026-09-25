<?php

namespace App\Service\Patient;

use App\DTO\Common\PaginatedResult;
use App\DTO\Patient\CreatePatientInput;
use App\DTO\Patient\PatientListQuery;
use App\DTO\Patient\UpdateDpiInput;
use App\DTO\Patient\UpdatePatientInput;
use App\Entity\CategorieTarifaire;
use App\Entity\Dpi;
use App\Entity\Filiere;
use App\Entity\OrganisationPartenaire;
use App\Entity\Patient;
use App\Entity\Structure;
use App\Exception\ConflictException;
use App\Exception\NotFoundException;
use App\Repository\DpiRepository;
use App\Repository\FiliereRepository;
use App\Repository\OrganisationPartenaireRepository;
use App\Repository\PatientRepository;
use App\Repository\StructureRepository;
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
        private readonly StructureRepository $structureRepository,
        private readonly FiliereRepository $filiereRepository,
        private readonly OrganisationPartenaireRepository $organisationPartenaireRepository,
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
            $query->filiereId,
            $query->organisationId,
            $query->typeInstitution,
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
            $query->filiereId,
            $query->organisationId,
            $query->typeInstitution,
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
            $patient->getCategorieTarifaire(),
            $patient->getStructure()?->getLibelle(),
            $patient->getNumeroAffiliation(),
            $patient->getCodeUkv(),
            $this->filiereLabel($patient->getFiliere()),
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
        $this->applyCategorieTarifaire(
            $patient,
            $input->categorieTarifaire,
            $input->structureId,
            $input->numeroAffiliation,
        );
        $this->applyUkvLink($patient, $input->codeUkv, $input->filiereId, $input->organisationId);

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
                $this->applyCategorieTarifaire(
                    $patient,
                    $input->categorieTarifaire,
                    $input->structureId,
                    $input->numeroAffiliation,
                );
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
        $this->applyCategorieTarifaire(
            $patient,
            $input->categorieTarifaire,
            $input->structureId,
            $input->numeroAffiliation,
        );
        $this->applyUkvLink($patient, $input->codeUkv, $input->filiereId, $input->organisationId);

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
            'categorieTarifaire' => $patient->getCategorieTarifaire(),
            'numeroAffiliation' => $patient->getNumeroAffiliation(),
            'codeUkv' => $patient->getCodeUkv(),
            'filiere' => $this->serializeFiliere($patient->getFiliere()),
            'organisation' => $this->serializeOrganisationPartenaire($patient->getOrganisation()),
            'structure' => $this->serializeStructure($patient->getStructure()),
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

    /** @return array<string, mixed> */
    public function buildMeta(): array
    {
        return [
            'statuses' => Patient::getStatuses(),
            'sexes' => Patient::getSexes(),
            'dpiStatuts' => Dpi::getStatuts(),
            'categoriesTarifaires' => CategorieTarifaire::definitions(),
            'structureTypes' => Structure::getTypes(),
            'structures' => array_map(
                [$this, 'serializeStructure'],
                $this->structureRepository->findActifs(),
            ),
            'filieres' => array_map(
                [$this, 'serializeFiliere'],
                $this->filiereRepository->findAllOrdered(),
            ),
            'organisations' => array_map(
                [$this, 'serializeOrganisationPartenaire'],
                $this->organisationPartenaireRepository->findActifsOrdered(),
            ),
            'typesInstitution' => array_map(
                static fn (string $code): array => [
                    'code' => $code,
                    'libelle' => match ($code) {
                        OrganisationPartenaire::TYPE_UNIVERSITE => 'Université',
                        OrganisationPartenaire::TYPE_INSTITUT_SUPERIEUR => 'Institut supérieur',
                        OrganisationPartenaire::TYPE_ECOLE => 'École',
                        OrganisationPartenaire::TYPE_ENTREPRISE => 'Entreprise',
                        OrganisationPartenaire::TYPE_ONG => 'ONG',
                        OrganisationPartenaire::TYPE_ADMINISTRATION => 'Administration',
                        OrganisationPartenaire::TYPE_AUTRE => 'Autre',
                        default => $code,
                    },
                    'requiresFiliere' => OrganisationPartenaire::TYPE_UNIVERSITE === $code,
                ],
                OrganisationPartenaire::getTypesInstitution(),
            ),
        ];
    }

    /** @return array{id: int, code: string, libelle: string, typeInstitution: string}|null */
    private function serializeOrganisationPartenaire(?OrganisationPartenaire $organisation): ?array
    {
        if (!$organisation instanceof OrganisationPartenaire) {
            return null;
        }

        return [
            'id' => (int) $organisation->getId(),
            'code' => (string) $organisation->getCode(),
            'libelle' => (string) $organisation->getLibelle(),
            'typeInstitution' => (string) $organisation->getTypeInstitution(),
            'requiresFiliere' => $organisation->requiresFiliere(),
        ];
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
            'organisationId' => $filiere->getOrganisation()?->getId(),
        ];
    }

    private function filiereLabel(?Filiere $filiere): ?string
    {
        if (!$filiere instanceof Filiere) {
            return null;
        }

        return trim(sprintf('%s — %s', $filiere->getCode() ?? '', $filiere->getLibelle() ?? ''));
    }

    private function applyUkvLink(Patient $patient, ?string $codeUkv, ?int $filiereId, ?int $organisationId = null): void
    {
        $normalizedCode = $this->normalizeOptionalText($codeUkv);
        if (null !== $normalizedCode) {
            $existing = $this->patientRepository->findOneByCodeUkv($normalizedCode);
            if (null !== $existing && $existing !== $patient) {
                throw new ConflictException('Ce code UKV est déjà attribué à un autre patient.');
            }
        }
        $patient->setCodeUkv($normalizedCode);

        $filiere = null;
        if (null !== $filiereId) {
            $filiere = $this->filiereRepository->find($filiereId);
            if (!$filiere instanceof Filiere) {
                throw new NotFoundException('Filière non trouvée.');
            }
        }
        $patient->setFiliere($filiere);

        $organisation = null;
        if ($filiere instanceof Filiere && $filiere->getOrganisation() instanceof OrganisationPartenaire) {
            $organisation = $filiere->getOrganisation();
        } elseif (null !== $organisationId) {
            $organisation = $this->organisationPartenaireRepository->find($organisationId);
            if (!$organisation instanceof OrganisationPartenaire) {
                throw new NotFoundException('Organisation partenaire non trouvée.');
            }
        }
        $patient->setOrganisation($organisation);
    }

    /** @return array<string, mixed>|null */
    private function serializeStructure(?Structure $structure): ?array
    {
        if (null === $structure) {
            return null;
        }

        return [
            'id' => $structure->getId(),
            'code' => $structure->getCode(),
            'libelle' => $structure->getLibelle(),
            'type' => $structure->getType(),
            'statut' => $structure->getStatut(),
        ];
    }

    private function applyCategorieTarifaire(
        Patient $patient,
        string $categorieTarifaire,
        ?int $structureId,
        ?string $numeroAffiliation,
    ): void {
        $categorie = CategorieTarifaire::normalize($categorieTarifaire);
        if (!CategorieTarifaire::isValid($categorie)) {
            throw new ConflictException('Catégorie tarifaire invalide.');
        }

        $structure = null;
        if (null !== $structureId) {
            $structure = $this->structureRepository->find($structureId);
            if (null === $structure) {
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

        $patient
            ->setCategorieTarifaire($categorie)
            ->setStructure($structure)
            ->setNumeroAffiliation($this->normalizeOptionalText($numeroAffiliation));
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

    private function parseDateNaissance(?string $value): ?\DateTime
    {
        $raw = trim((string) $value);
        if ('' === $raw) {
            return null;
        }
        $date = \DateTimeImmutable::createFromFormat('Y-m-d', $raw);
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
