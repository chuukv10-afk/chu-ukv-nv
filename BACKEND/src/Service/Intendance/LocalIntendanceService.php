<?php

namespace App\Service\Intendance;

use App\DTO\Common\PaginatedResult;
use App\DTO\Intendance\CreateLocalInput;
use App\DTO\Intendance\LocalListQuery;
use App\DTO\Intendance\UpdateLocalInput;
use App\Entity\LocalIntendance;
use App\Exception\ConflictException;
use App\Exception\NotFoundException;
use App\Repository\BienPatrimonialRepository;
use App\Repository\LocalIntendanceRepository;
use App\Repository\ServiceRepository;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Validator\Exception\ValidationFailedException;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final class LocalIntendanceService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly LocalIntendanceRepository $localIntendanceRepository,
        private readonly ServiceRepository $serviceRepository,
        private readonly BienPatrimonialRepository $bienPatrimonialRepository,
        private readonly ValidatorInterface $validator,
    ) {
    }

    public function paginate(LocalListQuery $query): PaginatedResult
    {
        $this->assertValid($query);
        $result = $this->localIntendanceRepository->paginate($query->page, $query->limit, $query->search, $query->serviceId);

        return new PaginatedResult(
            array_map([$this, 'serializeSummary'], $result['items']),
            $query->page,
            $query->limit,
            $result['total'],
        );
    }

    /** @return list<array<string, mixed>> */
    public function listActifs(int $serviceId): array
    {
        return array_map(
            [$this, 'serializeSummary'],
            $this->localIntendanceRepository->findActifsByService($serviceId),
        );
    }

    public function create(CreateLocalInput $input): LocalIntendance
    {
        $this->assertValid($input);
        $service = $this->serviceRepository->find($input->serviceId);
        if (null === $service) {
            throw new NotFoundException('Service non trouvé.');
        }

        $code = strtoupper(trim($input->code));
        if (null !== $this->localIntendanceRepository->findOneByServiceAndCode($service, $code)) {
            throw new ConflictException('Ce code local existe déjà dans ce service.');
        }

        $local = (new LocalIntendance())
            ->setService($service)
            ->setCode($code)
            ->setLibelle(trim($input->libelle))
            ->setOrdre($input->ordre)
            ->setStatut($this->normalizeStatut($input->statut))
            ->setCreatedAt(new \DateTimeImmutable());

        $this->entityManager->persist($local);

        try {
            $this->entityManager->flush();
        } catch (UniqueConstraintViolationException) {
            throw new ConflictException('Ce code local existe déjà dans ce service.');
        }

        return $local;
    }

    public function update(int $id, UpdateLocalInput $input): LocalIntendance
    {
        $this->assertValid($input);
        $local = $this->getById($id);
        $local
            ->setLibelle(trim($input->libelle))
            ->setOrdre($input->ordre)
            ->setStatut($this->normalizeStatut($input->statut));

        $this->entityManager->flush();

        return $local;
    }

    public function delete(int $id): void
    {
        $local = $this->getById($id);
        if ($this->bienPatrimonialRepository->countByLocal($local) > 0) {
            throw new ConflictException('Ce local est utilisé par au moins un bien. Passez-le en inactif.');
        }

        $this->entityManager->remove($local);
        $this->entityManager->flush();
    }

    public function getById(int $id): LocalIntendance
    {
        $local = $this->localIntendanceRepository->find($id);
        if (null === $local) {
            throw new NotFoundException('Local non trouvé.');
        }

        return $local;
    }

    /** @return array<string, mixed> */
    public function serializeSummary(LocalIntendance $local): array
    {
        $service = $local->getService();

        return [
            'id' => $local->getId(),
            'code' => $local->getCode(),
            'libelle' => $local->getLibelle(),
            'ordre' => $local->getOrdre(),
            'statut' => $local->getStatut(),
            'service' => $service ? [
                'id' => $service->getId(),
                'code' => $service->getCode(),
                'libelle' => $service->getLibelle(),
            ] : null,
            'createdAt' => $local->getCreatedAt()?->format(\DateTimeInterface::ATOM),
        ];
    }

    private function assertValid(object $input): void
    {
        $errors = $this->validator->validate($input);
        if (count($errors) > 0) {
            throw new ValidationFailedException($input, $errors);
        }
    }

    private function normalizeStatut(string $statut): string
    {
        $normalized = strtoupper(trim($statut));
        if (!in_array($normalized, LocalIntendance::getStatuts(), true)) {
            throw new ConflictException('Statut invalide.');
        }

        return $normalized;
    }
}
