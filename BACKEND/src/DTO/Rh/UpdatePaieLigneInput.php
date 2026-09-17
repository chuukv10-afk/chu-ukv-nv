<?php

namespace App\DTO\Rh;

use Symfony\Component\Validator\Constraints as Assert;

final class UpdatePaieLigneInput
{
    public function __construct(
        public ?bool $inclus = null,

        #[Assert\PositiveOrZero]
        public ?string $montant = null,

        #[Assert\Length(max: 255)]
        public ?string $motif = null,
    ) {
    }
}
