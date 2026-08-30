<?php

namespace App\Service\Organisation;

use App\DTO\Organisation\CreateDepartementInput;
use App\DTO\Organisation\UpdateDepartementInput;
use App\Exception\NotFoundException;

use App\Entity\Departement;
use App\Exception\ConflictException;
use App\Repository\DepartementRepository;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Validator\Exception\ValidationFailedException;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final class DepartementService
{
    public function __construct(
        private readonly EntityManagerInterface $eM,
        private readonly DepartementRepository $departementRepository,
        private readonly ValidatorInterface $validator,
    ) {}

    public function delete(string $id): void
    {
        $departement = $this->findById($id);
        if (null === $departement) {
            throw new NotFoundException('Département non trouvé.');
        }
        $this->departementRepository->remove($departement);
        $this->eM->flush();
    }

    public function update(int $id, UpdateDepartementInput $input): Departement
    {
        $departement = $this->findById($id);
        if (null === $departement) {
            throw new NotFoundException('Département non trouvé.');
        }
        $departement->setLibelle($input->libelle);
        $departement->setType($input->type);
    
        $this->eM->flush();
        return $departement;
    }

    public function findByCode(string $code): ?Departement
    {
        return $this->departementRepository->findOneBy(['code' => $code]) ?? null;
    }

    public function findById(string $id): ?Departement
    {
        return $this->departementRepository->find($id) ?? null;
    }

    public function findAll(): array
    {
        return $this->departementRepository->findAll() ?? [];
    }

    public function findOneBy(array $criteria): ?Departement
    {
        return $this->departementRepository->findOneBy($criteria);
    }

    public function findOneByOrFail(array $criteria): Departement
    {
        return $this->departementRepository->findOneByOrFail($criteria);
    }

    public function create(CreateDepartementInput $input): Departement
    {
        $errors = $this->validator->validate($input);
        if (count($errors) > 0) {
            throw new ValidationFailedException($input, $errors); // Symfony natif
        }

        if ($this->departementRepository->findOneBy(['code' => $input->code])) {
            throw new ConflictException('Ce code département existe déjà.');
        }

        $departement = (new Departement())
            ->setCode($input->code)
            ->setLibelle($input->libelle)
            ->setType($input->type)
            ->setCreatedAt(new \DateTimeImmutable());

        $this->eM->persist($departement);

        try {
            $this->eM->flush();
        } catch (UniqueConstraintViolationException) {
            throw new ConflictException('Ce code département existe déjà.');
        }

        return $departement;
    }
}