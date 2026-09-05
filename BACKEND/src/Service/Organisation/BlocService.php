<?php

namespace App\Service\Organisation;

use App\DTO\Common\PaginatedResult;
use App\DTO\Organisation\CreateBlocInput;
use App\DTO\Organisation\OrganisationListQuery;
use App\DTO\Organisation\UpdateBlocInput;
use App\Entity\Bloc;
use App\Exception\ConflictException;
use App\Exception\NotFoundException;
use App\Repository\BlocRepository;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Validator\Exception\ValidationFailedException;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final class BlocService
{
    public function __construct(
        private readonly EntityManagerInterface $eM,
        private readonly BlocRepository $blocRepository,
        private readonly ValidatorInterface $validator,
    ) {
    }

    public function delete(int $id): void
    {
        $bloc = $this->getById($id);
        if (!$bloc->getChambres()->isEmpty()) {
            throw new ConflictException('Ce bloc contient encore des chambres et ne peut pas être supprimé.');
        }

        $this->eM->remove($bloc);
        $this->eM->flush();
    }

    public function update(int $id, UpdateBlocInput $input): Bloc
    {
        $errors = $this->validator->validate($input);
        if (count($errors) > 0) {
            throw new ValidationFailedException($input, $errors);
        }

        $bloc = $this->getById($id);
        $bloc->setLibelle(trim($input->libelle));
        $this->eM->flush();

        return $bloc;
    }

    public function getById(int $id): Bloc
    {
        $bloc = $this->blocRepository->find($id);
        if (null === $bloc) {
            throw new NotFoundException('Bloc non trouvé.');
        }

        return $bloc;
    }

    /** @return list<Bloc> */
    public function findAll(): array
    {
        return $this->blocRepository->findBy([], ['libelle' => 'ASC']);
    }

    public function paginate(OrganisationListQuery $query): PaginatedResult
    {
        $errors = $this->validator->validate($query);
        if (count($errors) > 0) {
            throw new ValidationFailedException($query, $errors);
        }

        $result = $this->blocRepository->paginate($query->page, $query->limit, $query->search);

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
    public function buildExportRows(OrganisationListQuery $query): array
    {
        $errors = $this->validator->validate($query);
        if (count($errors) > 0) {
            throw new ValidationFailedException($query, $errors);
        }

        $items = $this->blocRepository->findForExport($query->search);

        return array_map(
            fn (Bloc $bloc): array => $this->buildExportRow($bloc),
            $items,
        );
    }

    /**
     * @return list<string|null>
     */
    public function buildExportRow(Bloc $bloc): array
    {
        return [
            $bloc->getCode(),
            $bloc->getLibelle(),
            (string) $bloc->getChambres()->count(),
        ];
    }

    /** @return array<string, mixed> */
    public function serializeSummary(Bloc $bloc): array
    {
        return [
            'id' => $bloc->getId(),
            'code' => $bloc->getCode(),
            'libelle' => $bloc->getLibelle(),
            'chambresCount' => $bloc->getChambres()->count(),
            'createdAt' => $bloc->getCreatedAt()?->format(\DateTimeInterface::ATOM),
        ];
    }

    public function create(CreateBlocInput $input): Bloc
    {
        $errors = $this->validator->validate($input);
        if (count($errors) > 0) {
            throw new ValidationFailedException($input, $errors);
        }

        $normalizedCode = strtoupper(trim($input->code));
        if ($this->blocRepository->findOneBy(['code' => $normalizedCode])) {
            throw new ConflictException('Ce code bloc existe déjà.');
        }

        $bloc = (new Bloc())
            ->setCode($normalizedCode)
            ->setLibelle(trim($input->libelle))
            ->setCreatedAt(new \DateTimeImmutable());

        $this->eM->persist($bloc);

        try {
            $this->eM->flush();
        } catch (UniqueConstraintViolationException) {
            throw new ConflictException('Ce code bloc existe déjà.');
        }

        return $bloc;
    }
}
