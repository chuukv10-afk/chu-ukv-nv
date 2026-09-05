<?php

namespace App\DTO\Clinique;

use Symfony\Component\Validator\Constraints as Assert;

final class CreateVisiteMesureInput
{
    public function __construct(
        #[Assert\NotNull]
        #[Assert\Positive]
        public ?int $signeVitalId = null,

        #[Assert\NotBlank]
        #[Assert\Length(max: 50)]
        public string $valeur = '',
    ) {
    }
}
