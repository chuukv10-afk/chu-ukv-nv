<?php

namespace App\DTO\Organisation;

use Symfony\Component\Validator\Constraints as Assert;

final class CreateLitInput
{
    public function __construct(
        #[Assert\NotBlank(message: 'Le code du lit est obligatoire.')]
        #[Assert\Length(max: 8, maxMessage: 'Le code ne peut pas dépasser {{ limit }} caractères.')]
        public string $code = '',

        #[Assert\NotBlank(message: 'Le numéro de lit est obligatoire.')]
        #[Assert\Length(max: 15, maxMessage: 'Le numéro de lit ne peut pas dépasser {{ limit }} caractères.')]
        public string $numeroLit = '',
    ) {
    }
}
