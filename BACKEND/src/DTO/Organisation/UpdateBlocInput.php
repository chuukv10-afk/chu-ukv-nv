<?php

namespace App\DTO\Organisation;

use Symfony\Component\Validator\Constraints as Assert;

final class UpdateBlocInput
{
    public function __construct(
        #[Assert\NotBlank(message: 'Le libellé du bloc est obligatoire.')]
        #[Assert\Length(max: 50, maxMessage: 'Le libellé ne peut pas dépasser {{ limit }} caractères.')]
        public string $libelle = '',

        #[Assert\NotBlank(message: 'La chambre du bloc est obligatoire.')]
        #[Assert\Length(max: 8, maxMessage: 'La chambre ne peut pas dépasser {{ limit }} caractères.')]
        public string $chambre = '',
    ) {
    }
}
