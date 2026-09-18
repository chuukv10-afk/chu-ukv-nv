<?php

namespace App\Service\Referentiel;

use App\DTO\Common\PaginatedResult;
use App\DTO\Referentiel\CreateOrganisationPartenaireInput;
use App\DTO\Referentiel\ReferentielListQuery;
use App\DTO\Referentiel\UpdateOrganisationPartenaireInput;
use App\Entity\OrganisationPartenaire;
use App\Exception\ConflictException;
use App\Exception\NotFoundException;
use App\Repository\OrganisationPartenaireRepository;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Validator\Exception\ValidationFailedException;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final class OrganisationPartenaireService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly OrganisationPartenaireRepository $repository,
        private readonly ValidatorInterface $validator,
    ) {
    }

    public function paginate(ReferentielListQuery $query): PaginatedResult
    {
        $this->assertValid($query);
        $result = $this->repository->paginate($query->page, $query->limit, $query->search);

        return new PaginatedResult(
            array_map([$this, 'serializeSummary'], $result['items']),
            $query->page,
            $query->limit,
            $result['total'],
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listLookup(): array
    {
        return array_map([$this, 'serializeLookup'], $this->repository->findActifsOrdered());
    }

    public function meta(): array
    {
        return [
            'typesInstitution' => array_map(
                static fn (string $code): array => [
                    'code' => $code,
                    'libelle' => self::typeLabel($code),
                    'requiresFiliere' => OrganisationPartenaire::TYPE_UNIVERSITE === $code,
                ],
                OrganisationPartenaire::getTypesInstitution(),
            ),
            'statuts' => OrganisationPartenaire::getStatuts(),
        ];
    }

    public function getById(int $id): OrganisationPartenaire
    {
        $organisation = $this->repository->find($id);
        if (!$organisation instanceof OrganisationPartenaire) {
            throw new NotFoundException('Organisation partenaire non trouvée.');
        }

        return $organisation;
    }

    public function create(CreateOrganisationPartenaireInput $input): OrganisationPartenaire
    {
        $this->assertValid($input);
        $code = strtoupper(trim($input->code));
        if ($this->repository->findOneBy(['code' => $code])) {
            throw new ConflictException('Ce code organisation existe déjà.');
        }

        $organisation = (new OrganisationPartenaire())
            ->setCode($code)
            ->setCreatedAt(new \DateTimeImmutable());
        $this->apply($organisation, $input->libelle, $input->typeInstitution, $input->statut);
        $this->entityManager->persist($organisation);
        try {
            $this->entityManager->flush();
        } catch (UniqueConstraintViolationException) {
            throw new ConflictException('Ce code organisation existe déjà.');
        }

        return $organisation;
    }

    public function update(int $id, UpdateOrganisationPartenaireInput $input): OrganisationPartenaire
    {
        $this->assertValid($input);
        $organisation = $this->getById($id);
        $this->apply($organisation, $input->libelle, $input->typeInstitution, $input->statut);
        $this->entityManager->flush();

        return $organisation;
    }

    public function delete(int $id): void
    {
        $organisation = $this->getById($id);
        if (!$organisation->getFilieres()->isEmpty()) {
            throw new ConflictException('Cette organisation a encore des filières et ne peut pas être supprimée.');
        }
        if (!$organisation->getPatients()->isEmpty()) {
            throw new ConflictException('Cette organisation est encore liée à des étudiants (DPI) et ne peut pas être supprimée.');
        }

        $this->entityManager->remove($organisation);
        $this->entityManager->flush();
    }

    /** @return array<string, mixed> */
    public function serializeSummary(OrganisationPartenaire $organisation): array
    {
        return [
            ...$this->serializeLookup($organisation),
            'statut' => $organisation->getStatut(),
            'filiereCount' => $organisation->getFilieres()->count(),
            'patientCount' => $organisation->getPatients()->count(),
            'createdAt' => $organisation->getCreatedAt()?->format(\DateTimeInterface::ATOM),
        ];
    }

    /**
     * @return array{
     *     id: int,
     *     code: string,
     *     libelle: string,
     *     typeInstitution: string,
     *     typeInstitutionLabel: string,
     *     requiresFiliere: bool
     * }
     */
    public function serializeLookup(OrganisationPartenaire $organisation): array
    {
        $type = (string) $organisation->getTypeInstitution();

        return [
            'id' => (int) $organisation->getId(),
            'code' => (string) $organisation->getCode(),
            'libelle' => (string) $organisation->getLibelle(),
            'typeInstitution' => $type,
            'typeInstitutionLabel' => self::typeLabel($type),
            'requiresFiliere' => $organisation->requiresFiliere(),
        ];
    }

    public static function typeLabel(string $type): string
    {
        return match ($type) {
            OrganisationPartenaire::TYPE_UNIVERSITE => 'Université',
            OrganisationPartenaire::TYPE_INSTITUT_SUPERIEUR => 'Institut supérieur',
            OrganisationPartenaire::TYPE_ECOLE => 'École',
            OrganisationPartenaire::TYPE_ENTREPRISE => 'Entreprise',
            OrganisationPartenaire::TYPE_ONG => 'ONG',
            OrganisationPartenaire::TYPE_ADMINISTRATION => 'Administration',
            OrganisationPartenaire::TYPE_AUTRE => 'Autre',
            default => $type,
        };
    }

    private function apply(
        OrganisationPartenaire $organisation,
        string $libelle,
        string $typeInstitution,
        string $statut,
    ): void {
        $organisation
            ->setLibelle(trim($libelle))
            ->setTypeInstitution(OrganisationPartenaire::normalizeType($typeInstitution))
            ->setStatut(strtoupper(trim($statut)) ?: OrganisationPartenaire::STATUT_ACTIF);
    }

    private function assertValid(object $input): void
    {
        $errors = $this->validator->validate($input);
        if (count($errors) > 0) {
            throw new ValidationFailedException($input, $errors);
        }
    }
}
