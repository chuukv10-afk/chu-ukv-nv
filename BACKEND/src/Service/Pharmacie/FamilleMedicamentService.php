<?php

namespace App\Service\Pharmacie;

use App\DTO\Common\PaginatedResult;
use App\DTO\Pharmacie\CreateFamilleMedicamentInput;
use App\DTO\Pharmacie\UpdateFamilleMedicamentInput;
use App\DTO\Referentiel\ReferentielListQuery;
use App\Entity\FamilleMedicament;
use App\Exception\ConflictException;
use App\Exception\NotFoundException;
use App\Repository\FamilleMedicamentRepository;
use App\Repository\MedicamentRepository;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Validator\Exception\ValidationFailedException;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final class FamilleMedicamentService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly FamilleMedicamentRepository $familleMedicamentRepository,
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

        $result = $this->familleMedicamentRepository->paginate($query->page, $query->limit, $query->search);

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
            $this->familleMedicamentRepository->findActifs(),
        );
    }

    public function create(CreateFamilleMedicamentInput $input): FamilleMedicament
    {
        $errors = $this->validator->validate($input);
        if (count($errors) > 0) {
            throw new ValidationFailedException($input, $errors);
        }

        $normalizedCode = strtoupper(trim($input->code));
        if ($this->familleMedicamentRepository->findOneBy(['code' => $normalizedCode])) {
            throw new ConflictException('Ce code famille existe déjà.');
        }

        $famille = (new FamilleMedicament())
            ->setCode($normalizedCode)
            ->setLibelle(trim($input->libelle))
            ->setOrdre($input->ordre)
            ->setStatut($this->normalizeStatut($input->statut))
            ->setCreatedAt(new \DateTimeImmutable());

        $this->entityManager->persist($famille);

        try {
            $this->entityManager->flush();
        } catch (UniqueConstraintViolationException) {
            throw new ConflictException('Ce code famille existe déjà.');
        }

        return $famille;
    }

    public function update(int $id, UpdateFamilleMedicamentInput $input): FamilleMedicament
    {
        $errors = $this->validator->validate($input);
        if (count($errors) > 0) {
            throw new ValidationFailedException($input, $errors);
        }

        $famille = $this->getById($id);
        $famille
            ->setLibelle(trim($input->libelle))
            ->setOrdre($input->ordre)
            ->setStatut($this->normalizeStatut($input->statut));

        $this->entityManager->flush();

        return $famille;
    }

    public function delete(int $id): void
    {
        $famille = $this->getById($id);
        if ($this->medicamentRepository->countByFamille($famille) > 0) {
            throw new ConflictException('Cette famille est utilisée par au moins un médicament.');
        }

        $this->entityManager->remove($famille);
        $this->entityManager->flush();
    }

    public function getById(int $id): FamilleMedicament
    {
        $famille = $this->familleMedicamentRepository->find($id);
        if (null === $famille) {
            throw new NotFoundException('Famille de médicament non trouvée.');
        }

        return $famille;
    }

    /** @return array<string, mixed> */
    public function serializeSummary(FamilleMedicament $famille): array
    {
        return [
            'id' => $famille->getId(),
            'code' => $famille->getCode(),
            'libelle' => $famille->getLibelle(),
            'ordre' => $famille->getOrdre(),
            'statut' => $famille->getStatut(),
            'createdAt' => $famille->getCreatedAt()?->format(\DateTimeInterface::ATOM),
        ];
    }

    private function normalizeStatut(string $statut): string
    {
        $normalized = strtoupper(trim($statut));
        if (!in_array($normalized, FamilleMedicament::getStatuts(), true)) {
            throw new ConflictException('Statut invalide.');
        }

        return $normalized;
    }
}
