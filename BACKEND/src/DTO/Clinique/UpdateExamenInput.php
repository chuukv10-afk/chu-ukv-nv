<?php

namespace App\DTO\Clinique;

use Symfony\Component\Validator\Constraints as Assert;

final class UpdateExamenInput
{
    public function __construct(
        #[Assert\NotBlank(message: 'Le libellé de l\'examen est obligatoire.')]
        #[Assert\Length(max: 100, maxMessage: 'Le libellé ne peut pas dépasser {{ limit }} caractères.')]
        public string $libelle = '',
    ) {
    }
}
