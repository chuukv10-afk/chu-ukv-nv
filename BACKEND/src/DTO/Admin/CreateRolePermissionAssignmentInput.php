<?php

namespace App\DTO\Admin;

use Symfony\Component\Validator\Constraints as Assert;

final class CreateRolePermissionAssignmentInput
{
    /**
     * @param list<string> $permissionIds
     */
    public function __construct(
        #[Assert\NotBlank(message: 'Le rôle est obligatoire.')]
        #[Assert\Uuid(message: 'Identifiant de rôle invalide.')]
        public string $roleId = '',

        #[Assert\Type(type: 'array', message: 'La liste des permissions doit être un tableau.')]
        public array $permissionIds = [],
    ) {
    }
}
