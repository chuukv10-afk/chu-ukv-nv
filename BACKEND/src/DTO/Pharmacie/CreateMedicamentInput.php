<?php

namespace App\DTO\Pharmacie;

use Symfony\Component\Validator\Constraints as Assert;

final class CreateMedicamentInput
{
    public function __construct(
        #[Assert\NotBlank(message: 'Le code est obligatoire.')]
        #[Assert\Length(max: 20)]
        public string $code = '',

        #[Assert\NotBlank(message: 'Le libellé est obligatoire.')]
        #[Assert\Length(max: 150)]
        public string $libelle = '',

        #[Assert\Length(max: 150)]
        public ?string $dci = null,

        #[Assert\Length(max: 80)]
        public ?string $forme = null,

        #[Assert\Length(max: 50)]
        public ?string $dosage = null,

        #[Assert\Positive(message: 'L\'unité est obligatoire.')]
        public int $uniteId = 0,

        #[Assert\Positive(message: 'La famille est obligatoire.')]
        public int $familleId = 0,

        #[Assert\NotBlank(message: 'Le prix de vente est obligatoire.')]
        #[Assert\PositiveOrZero]
        public string $prixVente = '0',

        #[Assert\PositiveOrZero]
        public int $seuilAlerte = 0,

        public string $statut = 'ACTIF',
    ) {
    }
}
