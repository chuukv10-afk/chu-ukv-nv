<?php

namespace App\DTO\Referentiel;

use Symfony\Component\Validator\Constraints as Assert;

final class UpdateTypeExamenInput
{
    public function __construct(
        #[Assert\NotBlank(message: 'Le libellé du type d\'examen est obligatoire.')]
        #[Assert\Length(max: 100, maxMessage: 'Le libellé ne peut pas dépasser {{ limit }} caractères.')]
        public string $libelle = '',
    ) {
    }
}
