<?php

namespace App\DTO\Referentiel;

use Symfony\Component\Validator\Constraints as Assert;

final class UpdateSpecialiteInput
{
    public function __construct(
        #[Assert\NotBlank(message: 'Le libellé de la spécialité est obligatoire.')]
        #[Assert\Length(max: 100, maxMessage: 'Le libellé ne peut pas dépasser {{ limit }} caractères.')]
        public string $libelle = '',
    ) {
    }
}
