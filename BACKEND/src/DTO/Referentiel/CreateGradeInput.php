<?php

namespace App\DTO\Referentiel;

use Symfony\Component\Validator\Constraints as Assert;

final class CreateGradeInput
{
    public function __construct(
        #[Assert\NotBlank(message: 'Le code du grade est obligatoire.')]
        #[Assert\Length(max: 8, maxMessage: 'Le code ne peut pas dépasser {{ limit }} caractères.')]
        public string $code = '',

        #[Assert\NotBlank(message: 'Le libellé du grade est obligatoire.')]
        #[Assert\Length(max: 100, maxMessage: 'Le libellé ne peut pas dépasser {{ limit }} caractères.')]
        public string $libelle = '',
    ) {
    }
}
