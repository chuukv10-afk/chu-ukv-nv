<?php

namespace App\Service\Organisation;

use App\DTO\Organisation\CreateServiceInput;
use App\DTO\Organisation\ServiceListQuery;
use App\DTO\Organisation\UpdateServiceInput;
use App\DTO\Common\PaginatedResult;
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
        $service = $this->getById($id);

        if (!$service->getPersonnels()->isEmpty()) {
            throw new ConflictException('Ce service est encore affecté à du personnel et ne peut pas être supprimé.');
        }

        $this->eM->remove($service);
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
        return $this->serviceRepository->findBy([], ['libelle' => 'ASC']);
    }

    public function paginate(ServiceListQuery $query): PaginatedResult
    {
        $errors = $this->validator->validate($query);
        if (count($errors) > 0) {
            throw new ValidationFailedException($query, $errors);
        }

        $result = $this->serviceRepository->paginate(
            $query->page,
            $query->limit,
            $query->search,
            $query->departementId,
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
    public function buildExportRows(ServiceListQuery $query): array
    {
        $errors = $this->validator->validate($query);
        if (count($errors) > 0) {
            throw new ValidationFailedException($query, $errors);
        }

        $items = $this->serviceRepository->findForExport($query->search, $query->departementId);

        return array_map(
            fn (Service $service): array => $this->buildExportRow($service),
            $items,
        );
    }

    /**
     * @return list<string|null>
     */
    public function buildExportRow(Service $service): array
    {
        return [
            $service->getCode(),
            $service->getLibelle(),
            $service->getDepartement()?->getLibelle(),
            (string) $service->getPersonnels()->count(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function serializeSummary(Service $service): array
    {
        $departement = $service->getDepartement();

        return [
            'id' => $service->getId(),
            'code' => $service->getCode(),
            'libelle' => $service->getLibelle(),
            'departementId' => $departement?->getId(),
            'departement' => null !== $departement ? [
                'id' => $departement->getId(),
                'code' => $departement->getCode(),
                'libelle' => $departement->getLibelle(),
            ] : null,
            'personnelCount' => $service->getPersonnels()->count(),
            'createdAt' => $service->getCreatedAt()?->format(\DateTimeInterface::ATOM),
        ];
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
            ->setCode(strtoupper(trim($input->code)))
            ->setLibelle(trim($input->libelle))
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
