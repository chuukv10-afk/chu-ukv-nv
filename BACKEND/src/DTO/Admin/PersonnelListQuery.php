<?php

namespace App\DTO\Admin;

use App\Entity\Personnel;
use Symfony\Component\Validator\Constraints as Assert;

final class PersonnelListQuery
{
    public function __construct(
        #[Assert\Positive]
        public int $page = 1,

        #[Assert\Range(min: 1, max: 100)]
        public int $limit = 10,

        #[Assert\Length(max: 100)]
        public ?string $search = null,

        public ?string $status = null,

        public ?string $type = null,

        #[Assert\Positive]
        public ?int $serviceId = null,

        #[Assert\Choice(choices: ['M', 'F'], message: 'Le sexe doit être M ou F.')]
        public ?string $sexe = null,
    ) {
        if ('' === $this->search) {
            $this->search = null;
        }

        if ('' === $this->status) {
            $this->status = null;
        }

        if ('' === $this->type) {
            $this->type = null;
        }

        if ('' === $this->sexe) {
            $this->sexe = null;
        }
    }

    #[Assert\Callback]
    public function validateFilters(\Symfony\Component\Validator\Context\ExecutionContextInterface $context): void
    {
        if (null !== $this->status && !Personnel::isValidStatus($this->status)) {
            $context->buildViolation('Statut invalide.')
                ->atPath('status')
                ->addViolation();
        }

        if (null !== $this->type && !Personnel::isValidType($this->type)) {
            $context->buildViolation('Type invalide.')
                ->atPath('type')
                ->addViolation();
        }
    }
}
