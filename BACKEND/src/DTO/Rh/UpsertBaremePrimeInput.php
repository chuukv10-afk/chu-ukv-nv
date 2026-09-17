<?php

namespace App\DTO\Rh;

use Symfony\Component\Validator\Constraints as Assert;

final class UpsertBaremePrimeInput
{
    public function __construct(
        public ?int $gradeId = null,

        #[Assert\NotBlank]
        #[Assert\Positive]
        public int $fonctionId = 0,

        #[Assert\NotBlank]
        #[Assert\PositiveOrZero]
        public string $montant = '',
    ) {
    }
}
