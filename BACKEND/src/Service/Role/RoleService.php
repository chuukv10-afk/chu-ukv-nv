<?php

namespace App\Service\Role;

use App\DTO\Admin\CreateRoleInput;
use App\DTO\Admin\UpdateRoleInput;
use App\Entity\PersonnelRole;
use App\Entity\Role;
use App\Exception\ConflictException;
use App\Exception\NotFoundException;
use App\Repository\RoleRepository;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Exception\ValidationFailedException;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final class RoleService
{
    private const SYSTEM_ROLE_CODES = [
        Role::CODE_ADMIN,
        Role::CODE_PERSONNEL,
    ];

    public function __construct(
        private readonly EntityManagerInterface $eM,
        private readonly RoleRepository $roleRepository,
        private readonly ValidatorInterface $validator,
    ) {
    }

    /**
     * @return list<Role>
     */
    public function findAll(): array
    {
        return $this->roleRepository->findBy([], ['code' => 'ASC']);
    }

    public function getById(string $id): Role
    {
        if (!Uuid::isValid($id)) {
            throw new NotFoundException('Rôle non trouvé.');
        }

        $role = $this->roleRepository->find(Uuid::fromString($id));
        if (null === $role) {
            throw new NotFoundException('Rôle non trouvé.');
        }

        return $role;
    }

    public function create(CreateRoleInput $input): Role
    {
        $errors = $this->validator->validate($input);
        if (count($errors) > 0) {
            throw new ValidationFailedException($input, $errors);
        }

        $normalizedCode = strtoupper(trim($input->code));
        if ($this->roleRepository->findOneBy(['code' => $normalizedCode])) {
            throw new ConflictException('Ce code rôle existe déjà.');
        }

        $role = (new Role())
            ->setCode($normalizedCode)
            ->setLibelle(trim($input->libelle))
            ->setPerimetre($input->perimetre)
            ->setCreatedAt(new \DateTimeImmutable());

        $this->eM->persist($role);

        try {
            $this->eM->flush();
        } catch (UniqueConstraintViolationException) {
            throw new ConflictException('Ce code rôle existe déjà.');
        }

        return $role;
    }

    public function update(string $id, UpdateRoleInput $input): Role
    {
        $errors = $this->validator->validate($input);
        if (count($errors) > 0) {
            throw new ValidationFailedException($input, $errors);
        }

        $role = $this->getById($id);
        $normalizedCode = strtoupper(trim($input->code));
        $normalizedPerimetre = PersonnelRole::normalizePerimetre($input->perimetre);

        if ($this->isSystemRole($role) && $role->getCode() !== $normalizedCode) {
            throw new ConflictException('Le code d\'un rôle système ne peut pas être modifié.');
        }

        if ($this->isSystemRole($role) && $role->getPerimetre() !== $normalizedPerimetre) {
            throw new ConflictException('Le périmètre d\'un rôle système ne peut pas être modifié.');
        }

        if (!$role->getPersonnelRoles()->isEmpty() && $role->getPerimetre() !== $normalizedPerimetre) {
            throw new ConflictException('Le périmètre ne peut pas être modifié tant que le rôle est affecté à du personnel.');
        }

        $existing = $this->roleRepository->findOneBy(['code' => $normalizedCode]);
        if (null !== $existing && $existing->getId()?->toRfc4122() !== $role->getId()?->toRfc4122()) {
            throw new ConflictException('Ce code rôle existe déjà.');
        }

        $role
            ->setCode($normalizedCode)
            ->setLibelle(trim($input->libelle))
            ->setPerimetre($normalizedPerimetre);

        try {
            $this->eM->flush();
        } catch (UniqueConstraintViolationException) {
            throw new ConflictException('Ce code rôle existe déjà.');
        }

        return $role;
    }

    public function delete(string $id): void
    {
        $role = $this->getById($id);

        if ($this->isSystemRole($role)) {
            throw new ConflictException('Un rôle système ne peut pas être supprimé.');
        }

        if (!$role->getPersonnelRoles()->isEmpty()) {
            throw new ConflictException('Ce rôle est encore affecté à du personnel et ne peut pas être supprimé.');
        }

        $this->eM->remove($role);
        $this->eM->flush();
    }

    private function isSystemRole(Role $role): bool
    {
        return in_array($role->getCode(), self::SYSTEM_ROLE_CODES, true);
    }
}
