<?php

namespace App\DTO\Pharmacie;

use Symfony\Component\Validator\Constraints as Assert;

final class AnnulerReceptionInput
{
    public function __construct(
        #[Assert\NotBlank(message: 'Le motif est obligatoire pour annuler une réception validée.')]
        #[Assert\Length(max: 255)]
        public ?string $motif = null,
    ) {
    }
}
