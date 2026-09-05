<?php

namespace App\Service\Support;

use App\Exception\ConflictException;
use App\Exception\NotFoundException;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ObjectRepository;
use Symfony\Component\Validator\Exception\ValidationFailedException;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * @template T of object
 */
abstract class AbstractCodeLibelleCrudService
{
    public function __construct(
        protected readonly EntityManagerInterface $eM,
        protected readonly ValidatorInterface $validator,
    ) {
    }

    abstract protected function repository(): ObjectRepository;

    /** @return T */
    abstract protected function instantiate(): object;

    abstract protected function duplicateCodeMessage(): string;

    abstract protected function notFoundMessage(): string;

    /** @param T $entity */
    abstract protected function setCode(object $entity, string $code): void;

    /** @param T $entity */
    abstract protected function setLibelle(object $entity, string $libelle): void;

    /** @param T $entity */
    abstract protected function setCreatedAt(object $entity, \DateTimeImmutable $createdAt): void;

    /**
     * @param T $entity
     *
     * @return array<string, mixed>
     */
    public function serializeSummary(object $entity): array
    {
        return [
            'id' => $entity->getId(),
            'code' => $entity->getCode(),
            'libelle' => $entity->getLibelle(),
            'createdAt' => $entity->getCreatedAt()?->format(\DateTimeInterface::ATOM),
        ];
    }

    public function delete(int $id): void
    {
        $this->eM->remove($this->getById($id));
        $this->eM->flush();
    }

    /**
     * @param object $input validated DTO with public string $libelle
     *
     * @return T
     */
    public function update(int $id, object $input): object
    {
        $errors = $this->validator->validate($input);
        if (count($errors) > 0) {
            throw new ValidationFailedException($input, $errors);
        }

        $entity = $this->getById($id);
        $this->setLibelle($entity, $input->libelle);
        $this->eM->flush();

        return $entity;
    }

    /** @return T */
    public function getById(int $id): object
    {
        $entity = $this->repository()->find($id);
        if (null === $entity) {
            throw new NotFoundException($this->notFoundMessage());
        }

        return $entity;
    }

    /** @return list<T> */
    public function findAll(): array
    {
        return $this->repository()->findAll();
    }

    /**
     * @param object $input validated DTO with public string $code and string $libelle
     *
     * @return T
     */
    public function create(object $input): object
    {
        $errors = $this->validator->validate($input);
        if (count($errors) > 0) {
            throw new ValidationFailedException($input, $errors);
        }

        $normalizedCode = strtoupper(trim($input->code));
        if ($this->repository()->findOneBy(['code' => $normalizedCode])) {
            throw new ConflictException($this->duplicateCodeMessage());
        }

        $entity = $this->instantiate();
        $this->setCode($entity, $normalizedCode);
        $this->setLibelle($entity, trim($input->libelle));
        $this->setCreatedAt($entity, new \DateTimeImmutable());

        $this->eM->persist($entity);

        try {
            $this->eM->flush();
        } catch (UniqueConstraintViolationException) {
            throw new ConflictException($this->duplicateCodeMessage());
        }

        return $entity;
    }
}
