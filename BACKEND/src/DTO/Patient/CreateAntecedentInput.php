<?php

namespace App\DTO\Patient;

use Symfony\Component\Validator\Constraints as Assert;

final class CreateAntecedentInput
{
    public function __construct(
        #[Assert\NotNull]
        #[Assert\Positive]
        public ?int $typeId = null,

        #[Assert\NotNull]
        #[Assert\Positive]
        public ?int $maladieId = null,
    ) {
    }
}
