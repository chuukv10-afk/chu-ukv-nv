<?php

namespace App\Service\Organisation;

use App\DTO\Organisation\CreateServiceInput;
use App\DTO\Organisation\UpdateServiceInput;
use App\Entity\Service;
use App\Exception\ConflictException;
use App\Exception\NotFoundException;
use App\Repository\ServiceRepository;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Validator\Exception\ValidationFailedException;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final class ServiceService
{
    public function __construct(
        private readonly EntityManagerInterface $eM,
        private readonly ServiceRepository $serviceRepository,
        private readonly DepartementService $departementService,
        private readonly ValidatorInterface $validator,
    ) {
    }

    public function delete(int $id): void
    {
        $this->eM->remove($this->getById($id));
        $this->eM->flush();
    }

    public function update(int $id, UpdateServiceInput $input): Service
    {
        $errors = $this->validator->validate($input);
        if (count($errors) > 0) {
            throw new ValidationFailedException($input, $errors);
        }

        $service = $this->getById($id);
        $departement = $this->departementService->getById($input->departementId);

        $service
            ->setLibelle($input->libelle)
            ->setDepartement($departement);

        $this->eM->flush();

        return $service;
    }

    public function getById(int $id): Service
    {
        $service = $this->serviceRepository->find($id);
        if (null === $service) {
            throw new NotFoundException('Service non trouvé.');
        }

        return $service;
    }

    /**
     * @return list<Service>
     */
    public function findAll(): array
    {
        return $this->serviceRepository->findAll();
    }

    public function create(CreateServiceInput $input): Service
    {
        $errors = $this->validator->validate($input);
        if (count($errors) > 0) {
            throw new ValidationFailedException($input, $errors);
        }

        if ($this->serviceRepository->findOneBy(['code' => $input->code])) {
            throw new ConflictException('Ce code service existe déjà.');
        }

        $departement = $this->departementService->getById($input->departementId);

        $service = (new Service())
            ->setCode($input->code)
            ->setLibelle($input->libelle)
            ->setDepartement($departement)
            ->setCreatedAt(new \DateTimeImmutable());

        $this->eM->persist($service);

        try {
            $this->eM->flush();
        } catch (UniqueConstraintViolationException) {
            throw new ConflictException('Ce code service existe déjà.');
        }

        return $service;
    }
}
