<?php

namespace App\DTO\Clinique;

use Symfony\Component\Validator\Constraints as Assert;

final class TriageSigneVitalInput
{
    public function __construct(
        #[Assert\Positive]
        public int $signeVitalId = 0,

        #[Assert\NotBlank(message: 'La valeur du signe vital est obligatoire.')]
        #[Assert\Length(max: 50)]
        public string $valeur = '',
    ) {
    }
}
