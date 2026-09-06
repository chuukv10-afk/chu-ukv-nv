<?php

namespace App\DTO\Pharmacie;

use Symfony\Component\Validator\Constraints as Assert;

final class DemandeServiceLigneInput
{
    public function __construct(
        #[Assert\Positive(message: 'Le médicament est obligatoire.')]
        public int $medicamentId = 0,

        #[Assert\Positive(message: 'La quantité doit être supérieure à 0.')]
        public int $quantite = 0,
    ) {
    }
}
