<?php

namespace App\DTO\Pharmacie;

use Symfony\Component\Validator\Constraints as Assert;

final class AnnulerVenteInput
{
    public function __construct(
        #[Assert\Length(max: 255)]
        public ?string $motif = null,
    ) {
    }
}
