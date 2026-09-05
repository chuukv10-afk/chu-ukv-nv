<?php

namespace App\Service\Permission;

use App\DTO\Admin\CreatePermissionInput;
use App\DTO\Admin\PermissionListQuery;
use App\DTO\Admin\UpdatePermissionInput;
use App\DTO\Common\PaginatedResult;
use App\Entity\Permission;
use App\Exception\ConflictException;
use App\Exception\NotFoundException;
use App\Repository\PermissionRepository;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Exception\ValidationFailedException;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final class PermissionService
{
    public function __construct(
        private readonly EntityManagerInterface $eM,
        private readonly PermissionRepository $permissionRepository,
        private readonly ValidatorInterface $validator,
    ) {
    }

    /**
     * @return list<Permission>
     */
    public function findAll(): array
    {
        return $this->permissionRepository->findBy([], ['module' => 'ASC', 'code' => 'ASC']);
    }

    public function paginate(PermissionListQuery $query): PaginatedResult
    {
        $errors = $this->validator->validate($query);
        if (count($errors) > 0) {
            throw new ValidationFailedException($query, $errors);
        }

        $result = $this->permissionRepository->paginate(
            $query->page,
            $query->limit,
            $query->module,
            $query->search,
        );

        return new PaginatedResult(
            $result['items'],
            $query->page,
            $query->limit,
            $result['total'],
        );
    }

    public function getById(string $id): Permission
    {
        if (!Uuid::isValid($id)) {
            throw new NotFoundException('Permission non trouvée.');
        }

        $permission = $this->permissionRepository->find(Uuid::fromString($id));
        if (null === $permission) {
            throw new NotFoundException('Permission non trouvée.');
        }

        return $permission;
    }

    public function create(CreatePermissionInput $input): Permission
    {
        $errors = $this->validator->validate($input);
        if (count($errors) > 0) {
            throw new ValidationFailedException($input, $errors);
        }

        $normalizedCode = strtolower(trim($input->code));
        if ($this->permissionRepository->findOneBy(['code' => $normalizedCode])) {
            throw new ConflictException('Ce code permission existe déjà.');
        }

        $permission = (new Permission())
            ->setCode($normalizedCode)
            ->setLibelle(trim($input->libelle))
            ->setDescription(null !== $input->description ? trim($input->description) : null)
            ->setModule($input->module)
            ->setCreatedAt(new \DateTimeImmutable());

        $this->eM->persist($permission);

        try {
            $this->eM->flush();
        } catch (UniqueConstraintViolationException) {
            throw new ConflictException('Ce code permission existe déjà.');
        }

        return $permission;
    }

    public function update(string $id, UpdatePermissionInput $input): Permission
    {
        $errors = $this->validator->validate($input);
        if (count($errors) > 0) {
            throw new ValidationFailedException($input, $errors);
        }

        $permission = $this->getById($id);
        $normalizedCode = strtolower(trim($input->code));

        $existing = $this->permissionRepository->findOneBy(['code' => $normalizedCode]);
        if (null !== $existing && $existing->getId()?->toRfc4122() !== $permission->getId()?->toRfc4122()) {
            throw new ConflictException('Ce code permission existe déjà.');
        }

        $permission
            ->setCode($normalizedCode)
            ->setLibelle(trim($input->libelle))
            ->setDescription(null !== $input->description ? trim($input->description) : null)
            ->setModule($input->module);

        try {
            $this->eM->flush();
        } catch (UniqueConstraintViolationException) {
            throw new ConflictException('Ce code permission existe déjà.');
        }

        return $permission;
    }

    public function delete(string $id): void
    {
        $permission = $this->getById($id);

        if (!$permission->getRoles()->isEmpty()) {
            throw new ConflictException('Cette permission est encore affectée à des rôles et ne peut pas être supprimée.');
        }

        $this->eM->remove($permission);
        $this->eM->flush();
    }
}
