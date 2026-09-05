<?php

namespace App\DTO\Clinique;

use Symfony\Component\Validator\Constraints as Assert;

final class CreateDemandeExamenInput
{
    public function __construct(
        #[Assert\NotNull]
        #[Assert\Positive]
        public ?int $examenId = null,

        #[Assert\Length(max: 255)]
        public ?string $noteMedecin = null,
    ) {
    }
}
