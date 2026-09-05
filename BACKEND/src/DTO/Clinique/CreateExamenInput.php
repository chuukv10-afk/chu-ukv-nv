<?php

namespace App\DTO\Clinique;

use Symfony\Component\Validator\Constraints as Assert;

final class CreateExamenInput
{
    public function __construct(
        #[Assert\NotBlank(message: 'Le code de l\'examen est obligatoire.')]
        #[Assert\Length(max: 8, maxMessage: 'Le code ne peut pas dépasser {{ limit }} caractères.')]
        public string $code = '',

        #[Assert\NotBlank(message: 'Le libellé de l\'examen est obligatoire.')]
        #[Assert\Length(max: 100, maxMessage: 'Le libellé ne peut pas dépasser {{ limit }} caractères.')]
        public string $libelle = '',

        #[Assert\NotNull(message: 'La catégorie est obligatoire.')]
        #[Assert\Positive(message: 'La catégorie est obligatoire.')]
        public ?int $typeExamenId = null,
    ) {
    }
}
