<?php

namespace App\DTO\Organisation;

use Symfony\Component\Validator\Constraints as Assert;

final class UpdateLitInput
{
    public function __construct(
        #[Assert\NotBlank(message: 'Le numéro de lit est obligatoire.')]
        #[Assert\Length(max: 15, maxMessage: 'Le numéro de lit ne peut pas dépasser {{ limit }} caractères.')]
        public string $numeroLit = '',
    ) {
    }
}
