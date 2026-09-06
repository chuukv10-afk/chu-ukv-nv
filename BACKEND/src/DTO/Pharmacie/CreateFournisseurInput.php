<?php

namespace App\DTO\Pharmacie;

use Symfony\Component\Validator\Constraints as Assert;

final class CreateFournisseurInput
{
    public function __construct(
        #[Assert\NotBlank(message: 'Le code est obligatoire.')]
        #[Assert\Length(max: 15)]
        public string $code = '',

        #[Assert\NotBlank(message: 'Le libellé est obligatoire.')]
        #[Assert\Length(max: 150)]
        public string $libelle = '',

        #[Assert\Length(max: 20)]
        public ?string $telephone = null,

        #[Assert\Length(max: 200)]
        public ?string $adresse = null,

        public string $statut = 'ACTIF',
    ) {
    }
}
