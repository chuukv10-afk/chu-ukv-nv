<?php

namespace App\DTO\Organisation;

use Symfony\Component\Validator\Constraints as Assert;

final class UpdateDepartementInput
{
    public function __construct(
        #[Assert\NotBlank(message: 'Le code du département est obligatoire.')]
        #[Assert\Length(max: 8, maxMessage: 'Le code ne peut pas dépasser {{ limit }} caractères.')]
        public string $code = '',

        #[Assert\NotBlank(message: 'Le libellé du département est obligatoire.')]
        #[Assert\Length(max: 100, maxMessage: 'Le libellé ne peut pas dépasser {{ limit }} caractères.')]
        public string $libelle = '',

        #[Assert\NotBlank(message: 'Le type du département est obligatoire.')]
        #[Assert\Length(max: 20, maxMessage: 'Le type ne peut pas dépasser {{ limit }} caractères.')]
        public string $type = '',
    ) {
    }
}