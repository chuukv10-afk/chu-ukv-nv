<?php

namespace App\DTO\Referentiel;

use Symfony\Component\Validator\Constraints as Assert;

final class UpdateFiliereInput
{
    public function __construct(
        #[Assert\NotBlank(message: 'Le libellé de la filière est obligatoire.')]
        #[Assert\Length(max: 150, maxMessage: 'Le libellé ne peut pas dépasser {{ limit }} caractères.')]
        public string $libelle = '',
    ) {
    }
}
