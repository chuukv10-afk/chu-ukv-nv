<?php

namespace App\DTO\Clinique;

use Symfony\Component\Validator\Constraints as Assert;

final class CreateMaladieInput
{
    public function __construct(
        #[Assert\NotBlank(message: 'Le code CIM-10 est obligatoire.')]
        #[Assert\Length(max: 15, maxMessage: 'Le code CIM-10 ne peut pas dépasser {{ limit }} caractères.')]
        public string $codeCim10 = '',

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
