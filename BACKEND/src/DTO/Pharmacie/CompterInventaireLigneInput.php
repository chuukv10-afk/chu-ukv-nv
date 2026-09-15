<?php

namespace App\DTO\Pharmacie;

use Symfony\Component\Validator\Constraints as Assert;

final class CompterInventaireLigneInput
{
    public function __construct(
        #[Assert\GreaterThanOrEqual(value: 0, message: 'La quantité comptée ne peut pas être négative.')]
        public ?int $quantiteComptee = null,
    ) {
    }
}
