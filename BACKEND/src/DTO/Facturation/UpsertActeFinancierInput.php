<?php

namespace App\DTO\Facturation;

use App\Entity\ActeFinancier;
use Symfony\Component\Validator\Constraints as Assert;

final class UpsertActeFinancierInput
{
    public function __construct(
        #[Assert\NotBlank(message: 'Le service est obligatoire.')]
        #[Assert\Length(max: 40)]
        public string $serviceGrille = '',

        #[Assert\Length(max: 80)]
        public ?string $sousCategorie = null,

        #[Assert\NotBlank(message: 'Le libellé est obligatoire.')]
        #[Assert\Length(max: 180)]
        public string $libelle = '',

        public string $tarifA0 = '0',

        public string $tarifA1 = '0',

        public string $tarifA = '0',

        public string $tarifB = '0',

        public string $tarifC = '0',

        public string $statut = ActeFinancier::STATUT_ACTIF,
    ) {
    }
}
