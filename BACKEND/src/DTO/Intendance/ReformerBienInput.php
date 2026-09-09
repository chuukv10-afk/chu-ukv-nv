<?php

namespace App\DTO\Intendance;

use Symfony\Component\Validator\Constraints as Assert;

final class ReformerBienInput
{
    public function __construct(
        #[Assert\NotBlank(message: 'Le motif de réforme est obligatoire.')]
        #[Assert\Length(max: 500)]
        public string $motif = '',
    ) {
    }
}
