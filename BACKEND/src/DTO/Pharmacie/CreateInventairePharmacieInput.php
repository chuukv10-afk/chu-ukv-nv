<?php

namespace App\DTO\Pharmacie;

use Symfony\Component\Validator\Constraints as Assert;

final class CreateInventairePharmacieInput
{
    public function __construct(
        #[Assert\NotBlank(message: 'Le libellé de la campagne est obligatoire.')]
        #[Assert\Length(max: 150)]
        public string $libelle = '',

        #[Assert\Length(max: 255)]
        public ?string $notes = null,
    ) {
    }
}
