<?php

namespace App\DTO\Rh;

use Symfony\Component\Validator\Constraints as Assert;

final class OpenPaiePeriodeInput
{
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Range(min: 2020, max: 2100)]
        public int $annee = 0,

        #[Assert\NotBlank]
        #[Assert\Range(min: 1, max: 12)]
        public int $mois = 0,
    ) {
    }
}
