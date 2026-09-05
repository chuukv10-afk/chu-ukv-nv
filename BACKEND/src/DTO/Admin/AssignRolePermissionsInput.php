<?php

namespace App\DTO\Admin;

use Symfony\Component\Validator\Constraints as Assert;

final class AssignRolePermissionsInput
{
    /**
     * @param list<string> $permissionIds
     */
    public function __construct(
        #[Assert\Type(type: 'array', message: 'La liste des permissions doit être un tableau.')]
        public array $permissionIds = [],
    ) {
    }
}
