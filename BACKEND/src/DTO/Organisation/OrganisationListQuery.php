<?php

namespace App\DTO\Organisation;

use Symfony\Component\Validator\Constraints as Assert;

final class OrganisationListQuery
{
    public function __construct(
        #[Assert\Positive]
        public int $page = 1,

        #[Assert\Range(min: 1, max: 100)]
        public int $limit = 10,

        #[Assert\Length(max: 100)]
        public ?string $search = null,

        #[Assert\Length(max: 20)]
        public ?string $type = null,

        #[Assert\Positive]
        public ?int $blocId = null,

        #[Assert\Positive]
        public ?int $chambreId = null,
    ) {
        if ('' === $this->search) {
            $this->search = null;
        }

        if ('' === $this->type) {
            $this->type = null;
        }
    }
}
