<?php

namespace App\DTO\Referentiel;

use Symfony\Component\Validator\Constraints as Assert;

final class UpdateTypeAntecedentInput
{
    public function __construct(
        #[Assert\NotBlank(message: 'Le libellé du type d\'antécédent est obligatoire.')]
        #[Assert\Length(max: 50, maxMessage: 'Le libellé ne peut pas dépasser {{ limit }} caractères.')]
        public string $libelle = '',
    ) {
    }
}
