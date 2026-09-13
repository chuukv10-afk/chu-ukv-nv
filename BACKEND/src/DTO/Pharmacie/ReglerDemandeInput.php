<?php

namespace App\DTO\Pharmacie;

use Symfony\Component\Validator\Constraints as Assert;

final class ReglerDemandeInput
{
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Choice(choices: ['ESPECES', 'MOBILE'])]
        public string $modePaiement = 'ESPECES',

        #[Assert\Positive(message: 'Le montant encaissé doit être supérieur à 0.')]
        public mixed $montant = null,
    ) {
        if ('' === $this->montant || false === $this->montant) {
            $this->montant = null;
        }
    }
}
