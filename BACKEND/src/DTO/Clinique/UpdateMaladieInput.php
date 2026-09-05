<?php

namespace App\DTO\Clinique;

use Symfony\Component\Validator\Constraints as Assert;

final class UpdateMaladieInput
{
    public function __construct(
        #[Assert\NotBlank(message: 'Le libellé est obligatoire.')]
        #[Assert\Length(max: 255, maxMessage: 'Le libellé ne peut pas dépasser {{ limit }} caractères.')]
        public string $libelle = '',

        #[Assert\Length(max: 20, maxMessage: 'Le chapitre ne peut pas dépasser {{ limit }} caractères.')]
        public ?string $chapitre = null,
    ) {
        if ('' === $this->chapitre) {
            $this->chapitre = null;
        }
    }
}
