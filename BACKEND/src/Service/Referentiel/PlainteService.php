<?php

namespace App\Service\Referentiel;

use App\DTO\Common\PaginatedResult;
use App\DTO\Referentiel\CreatePlainteInput;
use App\DTO\Referentiel\ReferentielListQuery;
use App\DTO\Referentiel\UpdatePlainteInput;
use App\Entity\Plainte;
use App\Exception\ConflictException;
use App\Exception\NotFoundException;
use App\Repository\PlainteRepository;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Validator\Exception\ValidationFailedException;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final class PlainteService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly PlainteRepository $plainteRepository,
        private readonly ValidatorInterface $validator,
    ) {
    }

    public function paginate(ReferentielListQuery $query): PaginatedResult
    {
        $errors = $this->validator->validate($query);
        if (count($errors) > 0) {
            throw new ValidationFailedException($query, $errors);
        }

        $result = $this->plainteRepository->paginate($query->page, $query->limit, $query->search);

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
            $this->plainteRepository->findActifs(),
        );
    }

    public function create(CreatePlainteInput $input): Plainte
    {
        $errors = $this->validator->validate($input);
        if (count($errors) > 0) {
            throw new ValidationFailedException($input, $errors);
        }

        $normalizedCode = strtoupper(trim($input->code));
        if ($this->plainteRepository->findOneBy(['code' => $normalizedCode])) {
            throw new ConflictException('Ce code plainte existe déjà.');
        }

        $plainte = (new Plainte())
            ->setCode($normalizedCode)
            ->setLibelle(trim($input->libelle))
            ->setOrdre($input->ordre)
            ->setStatut($this->normalizeStatut($input->statut))
            ->setCreatedAt(new \DateTimeImmutable());

        $this->entityManager->persist($plainte);

        try {
            $this->entityManager->flush();
        } catch (UniqueConstraintViolationException) {
            throw new ConflictException('Ce code plainte existe déjà.');
        }

        return $plainte;
    }

    public function update(int $id, UpdatePlainteInput $input): Plainte
    {
        $errors = $this->validator->validate($input);
        if (count($errors) > 0) {
            throw new ValidationFailedException($input, $errors);
        }

        $plainte = $this->getById($id);
        $plainte
            ->setLibelle(trim($input->libelle))
            ->setOrdre($input->ordre)
            ->setStatut($this->normalizeStatut($input->statut));

        $this->entityManager->flush();

        return $plainte;
    }

    public function delete(int $id): void
    {
        $this->entityManager->remove($this->getById($id));
        $this->entityManager->flush();
    }

    public function getById(int $id): Plainte
    {
        $plainte = $this->plainteRepository->find($id);
        if (null === $plainte) {
            throw new NotFoundException('Plainte non trouvée.');
        }

        return $plainte;
    }

    /** @return array<string, mixed> */
    public function serializeSummary(Plainte $plainte): array
    {
        return [
            'id' => $plainte->getId(),
            'code' => $plainte->getCode(),
            'libelle' => $plainte->getLibelle(),
            'ordre' => $plainte->getOrdre(),
            'statut' => $plainte->getStatut(),
            'createdAt' => $plainte->getCreatedAt()?->format(\DateTimeInterface::ATOM),
        ];
    }

    private function normalizeStatut(string $statut): string
    {
        $normalized = strtoupper(trim($statut));
        if (!in_array($normalized, Plainte::getStatuts(), true)) {
            throw new ConflictException('Statut invalide.');
        }

        return $normalized;
    }
}
