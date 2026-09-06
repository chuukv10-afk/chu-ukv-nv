<?php

namespace App\DTO\Pharmacie;

use Symfony\Component\Validator\Constraints as Assert;

final class ReglerDemandeInput
{
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Choice(choices: ['ESPECES', 'MOBILE'])]
        public string $modePaiement = 'ESPECES',
    ) {
    }
}
