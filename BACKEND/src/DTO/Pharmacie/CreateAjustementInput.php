<?php

namespace App\DTO\Pharmacie;

use Symfony\Component\Validator\Constraints as Assert;

final class CreateAjustementInput
{
    public function __construct(
        #[Assert\Positive(message: 'Le lot est obligatoire.')]
        public int $lotId = 0,

        #[Assert\NotBlank]
        #[Assert\Choice(choices: ['AJUSTEMENT_PLUS', 'AJUSTEMENT_MOINS', 'SORTIE_PERTE', 'SORTIE_PEREMPTION'])]
        public string $type = 'AJUSTEMENT_MOINS',

        #[Assert\Positive(message: 'La quantité doit être supérieure à 0.')]
        public int $quantite = 0,

        #[Assert\NotBlank(message: 'Le motif est obligatoire.')]
        #[Assert\Length(max: 255)]
        public string $motif = '',
    ) {
    }
}
