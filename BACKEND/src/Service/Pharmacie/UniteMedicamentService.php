<?php

namespace App\Service\Pharmacie;

use App\DTO\Common\PaginatedResult;
use App\DTO\Pharmacie\CreateUniteMedicamentInput;
use App\DTO\Pharmacie\UpdateUniteMedicamentInput;
use App\DTO\Referentiel\ReferentielListQuery;
use App\Entity\UniteMedicament;
use App\Exception\ConflictException;
use App\Exception\NotFoundException;
use App\Repository\MedicamentRepository;
use App\Repository\UniteMedicamentRepository;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Validator\Exception\ValidationFailedException;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final class UniteMedicamentService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly UniteMedicamentRepository $uniteMedicamentRepository,
        private readonly MedicamentRepository $medicamentRepository,
        private readonly ValidatorInterface $validator,
    ) {
    }

    public function paginate(ReferentielListQuery $query): PaginatedResult
    {
        $errors = $this->validator->validate($query);
        if (count($errors) > 0) {
            throw new ValidationFailedException($query, $errors);
        }

        $result = $this->uniteMedicamentRepository->paginate($query->page, $query->limit, $query->search);

        return new PaginatedResult(
            array_map([$this, 'serializeSummary'], $result['items']),
            $query->page,
            $query->limit,
            $result['total'],
        );
    }

    /** @return list<array<string, mixed>> */
    public function listActifs(): array
    {
        return array_map(
            [$this, 'serializeSummary'],
            $this->uniteMedicamentRepository->findActifs(),
        );
    }

    public function create(CreateUniteMedicamentInput $input): UniteMedicament
    {
        $errors = $this->validator->validate($input);
        if (count($errors) > 0) {
            throw new ValidationFailedException($input, $errors);
        }

        $normalizedCode = strtoupper(trim($input->code));
        if ($this->uniteMedicamentRepository->findOneBy(['code' => $normalizedCode])) {
            throw new ConflictException('Ce code unité existe déjà.');
        }

        $unite = (new UniteMedicament())
            ->setCode($normalizedCode)
            ->setLibelle(trim($input->libelle))
            ->setOrdre($input->ordre)
            ->setStatut($this->normalizeStatut($input->statut))
            ->setCreatedAt(new \DateTimeImmutable());

        $this->entityManager->persist($unite);

        try {
            $this->entityManager->flush();
        } catch (UniqueConstraintViolationException) {
            throw new ConflictException('Ce code unité existe déjà.');
        }

        return $unite;
    }

    public function update(int $id, UpdateUniteMedicamentInput $input): UniteMedicament
    {
        $errors = $this->validator->validate($input);
        if (count($errors) > 0) {
            throw new ValidationFailedException($input, $errors);
        }

        $unite = $this->getById($id);
        $unite
            ->setLibelle(trim($input->libelle))
            ->setOrdre($input->ordre)
            ->setStatut($this->normalizeStatut($input->statut));

        $this->entityManager->flush();

        return $unite;
    }

    public function delete(int $id): void
    {
        $unite = $this->getById($id);
        if ($this->medicamentRepository->countByUnite($unite) > 0) {
            throw new ConflictException('Cette unité est utilisée par au moins un médicament.');
        }

        $this->entityManager->remove($unite);
        $this->entityManager->flush();
    }

    public function getById(int $id): UniteMedicament
    {
        $unite = $this->uniteMedicamentRepository->find($id);
        if (null === $unite) {
            throw new NotFoundException('Unité de médicament non trouvée.');
        }

        return $unite;
    }

    /** @return array<string, mixed> */
    public function serializeSummary(UniteMedicament $unite): array
    {
        return [
            'id' => $unite->getId(),
            'code' => $unite->getCode(),
            'libelle' => $unite->getLibelle(),
            'ordre' => $unite->getOrdre(),
            'statut' => $unite->getStatut(),
            'createdAt' => $unite->getCreatedAt()?->format(\DateTimeInterface::ATOM),
        ];
    }

    private function normalizeStatut(string $statut): string
    {
        $normalized = strtoupper(trim($statut));
        if (!in_array($normalized, UniteMedicament::getStatuts(), true)) {
            throw new ConflictException('Statut invalide.');
        }

        return $normalized;
    }
}
