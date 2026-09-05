<?php

namespace App\DTO\Admin;

use Symfony\Component\Validator\Constraints as Assert;

final class PersonnelRoleAssignmentInput
{
    public function __construct(
        #[Assert\NotBlank(message: 'Le rôle est obligatoire.')]
        #[Assert\Uuid(message: 'Identifiant de rôle invalide.')]
        public string $roleId = '',

        #[Assert\Positive(message: 'Identifiant de service invalide.')]
        public ?int $serviceId = null,

        #[Assert\Positive(message: 'Identifiant de département invalide.')]
        public ?int $departementId = null,
    ) {
    }
}
