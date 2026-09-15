<?php

namespace App\DTO\Facturation;

use App\Entity\Structure;
use Symfony\Component\Validator\Constraints as Assert;

final class CreateStructureInput
{
    public function __construct(
        #[Assert\NotBlank(message: 'Le code est obligatoire.')]
        #[Assert\Length(max: 15)]
        public string $code = '',

        #[Assert\NotBlank(message: 'Le libellé est obligatoire.')]
        #[Assert\Length(max: 150)]
        public string $libelle = '',

        #[Assert\NotBlank(message: 'Le type est obligatoire.')]
        #[Assert\Choice(callback: [Structure::class, 'getTypes'], message: 'Type de structure invalide.')]
        public string $type = '',

        #[Assert\Length(max: 20)]
        public ?string $telephone = null,

        #[Assert\Length(max: 200)]
        public ?string $adresse = null,

        public string $statut = Structure::STATUT_ACTIF,
    ) {
    }
}
