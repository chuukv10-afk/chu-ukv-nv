<?php

namespace App\DTO\Referentiel;

use Symfony\Component\Validator\Constraints as Assert;

final class CreateFiliereInput
{
    public function __construct(
        #[Assert\NotBlank(message: 'Le code de la filière est obligatoire.')]
        #[Assert\Length(max: 12, maxMessage: 'Le code ne peut pas dépasser {{ limit }} caractères.')]
        public string $code = '',

        #[Assert\NotBlank(message: 'Le libellé de la filière est obligatoire.')]
        #[Assert\Length(max: 150, maxMessage: 'Le libellé ne peut pas dépasser {{ limit }} caractères.')]
        public string $libelle = '',
    ) {
    }
}
