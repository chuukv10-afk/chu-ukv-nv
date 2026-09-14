<?php

namespace App\DTO\Imagerie;

use Symfony\Component\Validator\Constraints as Assert;

final class InterpretEtudeImagerieInput
{
    public function __construct(
        #[Assert\Length(max: 4000)]
        public ?string $technique = null,

        #[Assert\NotBlank(message: 'Les constatations sont obligatoires.')]
        #[Assert\Length(max: 8000)]
        public string $constatations = '',

        #[Assert\NotBlank(message: 'La conclusion est obligatoire.')]
        #[Assert\Length(max: 4000)]
        public string $conclusion = '',
    ) {
    }
}
