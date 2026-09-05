<?php

namespace App\DTO\Organisation;

use Symfony\Component\Validator\Constraints as Assert;

final class UpdateChambreInput
{
    public function __construct(
        #[Assert\NotBlank(message: 'Le libellé de la chambre est obligatoire.')]
        #[Assert\Length(max: 50, maxMessage: 'Le libellé ne peut pas dépasser {{ limit }} caractères.')]
        public string $libelle = '',

        #[Assert\NotBlank(message: 'Le type de la chambre est obligatoire.')]
        #[Assert\Length(max: 8, maxMessage: 'Le type ne peut pas dépasser {{ limit }} caractères.')]
        public string $type = '',

        #[Assert\NotNull(message: 'Le bloc est obligatoire.')]
        #[Assert\Positive(message: 'Le bloc est obligatoire.')]
        public ?int $blocId = null,
    ) {
    }
}
