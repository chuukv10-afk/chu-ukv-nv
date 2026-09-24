<?php

namespace App\Service\Facturation;

use App\DTO\Common\PaginatedResult;
use App\DTO\Facturation\CreateStructureInput;
use App\DTO\Facturation\FacturationListQuery;
use App\DTO\Facturation\UpdateStructureInput;
use App\Entity\Facture;
use App\Entity\Patient;
use App\Entity\Structure;
use App\Entity\Visite;
use App\Exception\ConflictException;
use App\Exception\NotFoundException;
use App\Repository\StructureRepository;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Validator\Exception\ValidationFailedException;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final class StructureService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly StructureRepository $structureRepository,
        private readonly ValidatorInterface $validator,
    ) {
    }

    public function paginate(FacturationListQuery $query): PaginatedResult
    {
        $this->assertValid($query);
        $result = $this->structureRepository->paginate(
            $query->page,
            $query->limit,
            $query->search,
            $query->type,
            $query->statut,
        );

        return new PaginatedResult(
            array_map([$this, 'serializeSummary'], $result['items']),
            $query->page,
            $query->limit,
            $result['total'],
        );
    }

    /** @return list<array<string, mixed>> */
    public function listActifs(?string $type = null): array
    {
        return array_map([$this, 'serializeSummary'], $this->structureRepository->findActifs($type));
    }

    public function create(CreateStructureInput $input): Structure
    {
        $this->assertValid($input);
        $code = strtoupper(trim($input->code));
        if ($this->structureRepository->findOneBy(['code' => $code])) {
            throw new ConflictException('Ce code structure existe déjà.');
        }

        $structure = (new Structure())
            ->setCode($code)
            ->setCreatedAt(new \DateTimeImmutable());
        $this->apply($structure, $input->libelle, $input->type, $input->telephone, $input->adresse, $input->statut);

        $this->entityManager->persist($structure);
        try {
            $this->entityManager->flush();
        } catch (UniqueConstraintViolationException) {
            throw new ConflictException('Ce code structure existe déjà.');
        }

        return $structure;
    }

    public function update(int $id, UpdateStructureInput $input): Structure
    {
        $this->assertValid($input);
        $structure = $this->getById($id);
        $this->apply($structure, $input->libelle, $input->type, $input->telephone, $input->adresse, $input->statut);
        $this->entityManager->flush();

        return $structure;
    }

    public function delete(int $id): void
    {
        $structure = $this->getById($id);
        $patientCount = (int) $this->entityManager->getRepository(Patient::class)->count(['structure' => $structure]);
        if ($patientCount > 0) {
            throw new ConflictException('Cette structure est liée à des patients.');
        }
        $visiteCount = (int) $this->entityManager->getRepository(Visite::class)->count(['structure' => $structure]);
        if ($visiteCount > 0) {
            throw new ConflictException('Cette structure est liée à des visites.');
        }
        $factureCount = (int) $this->entityManager->getRepository(Facture::class)->count(['structure' => $structure]);
        if ($factureCount > 0) {
            throw new ConflictException('Cette structure est liée à des factures.');
        }

        $this->entityManager->remove($structure);
        $this->entityManager->flush();
    }

    public function getById(int $id): Structure
    {
        $structure = $this->structureRepository->find($id);
        if (null === $structure) {
            throw new NotFoundException('Structure non trouvée.');
        }

        return $structure;
    }

    /** @return array<string, mixed> */
    public function serializeSummary(Structure $structure): array
    {
        return [
            'id' => $structure->getId(),
            'code' => $structure->getCode(),
            'libelle' => $structure->getLibelle(),
            'type' => $structure->getType(),
            'telephone' => $structure->getTelephone(),
            'adresse' => $structure->getAdresse(),
            'statut' => $structure->getStatut(),
            'createdAt' => $structure->getCreatedAt()?->format(\DateTimeInterface::ATOM),
        ];
    }

    private function apply(
        Structure $structure,
        string $libelle,
        string $type,
        ?string $telephone,
        ?string $adresse,
        string $statut,
    ): void {
        $normalizedType = Structure::normalizeType($type);
        if (!Structure::isValidType($normalizedType)) {
            throw new ConflictException('Type de structure invalide.');
        }

        $normalizedStatut = strtoupper(trim($statut));
        if (!in_array($normalizedStatut, Structure::getStatuts(), true)) {
            throw new ConflictException('Statut invalide.');
        }

        $structure
            ->setLibelle(trim($libelle))
            ->setType($normalizedType)
            ->setTelephone($this->nullable($telephone))
            ->setAdresse($this->nullable($adresse))
            ->setStatut($normalizedStatut);
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
