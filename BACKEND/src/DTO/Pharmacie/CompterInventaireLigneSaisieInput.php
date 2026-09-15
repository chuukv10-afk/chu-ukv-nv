<?php

namespace App\DTO\Pharmacie;

use Symfony\Component\Validator\Constraints as Assert;

final class CompterInventaireLigneSaisieInput
{
    public function __construct(
        #[Assert\Positive(message: 'La ligne est obligatoire.')]
        public int $ligneId = 0,

        #[Assert\GreaterThanOrEqual(value: 0, message: 'La quantité comptée ne peut pas être négative.')]
        public ?int $quantiteComptee = null,

        public ?string $datePeremption = null,
    ) {
        if (null !== $this->datePeremption) {
            $normalized = UpsertVenteInput::toDateOnly($this->datePeremption);
            $this->datePeremption = (null === $normalized || '' === $normalized) ? null : $normalized;
        }
    }
}
