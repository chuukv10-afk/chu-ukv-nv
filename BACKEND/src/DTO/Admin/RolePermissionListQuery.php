<?php

namespace App\DTO\Admin;

use App\Entity\Permission;
use Symfony\Component\Validator\Constraints as Assert;

final class RolePermissionListQuery
{
    public function __construct(
        #[Assert\Positive]
        public int $page = 1,

        #[Assert\Range(min: 1, max: 100)]
        public int $limit = 10,

        #[Assert\Length(max: 100)]
        public ?string $search = null,

        public ?string $module = null,
    ) {
        if ('' === $this->search) {
            $this->search = null;
        }

        if ('' === $this->module) {
            $this->module = null;
        }
    }

    #[Assert\Callback]
    public function validateModule(\Symfony\Component\Validator\Context\ExecutionContextInterface $context): void
    {
        if (null === $this->module) {
            return;
        }

        if (!Permission::isValidModule($this->module)) {
            $context->buildViolation('Module invalide.')
                ->atPath('module')
                ->addViolation();
        }
    }
}
