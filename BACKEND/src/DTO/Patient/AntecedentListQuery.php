<?php

namespace App\DTO\Patient;

use Symfony\Component\Validator\Constraints as Assert;

final class AntecedentListQuery
{
    public function __construct(
        #[Assert\Positive]
        public int $page = 1,

        #[Assert\Range(min: 1, max: 200)]
        public int $limit = 50,

        #[Assert\Positive]
        public ?int $typeId = null,
    ) {
    }
}
