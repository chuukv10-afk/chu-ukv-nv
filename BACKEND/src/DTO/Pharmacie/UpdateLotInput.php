<?php

namespace App\DTO\Pharmacie;

use Symfony\Component\Validator\Constraints as Assert;

final class UpdateLotInput
{
    public function __construct(
        #[Assert\NotBlank(message: 'Le numéro de lot est obligatoire.')]
        #[Assert\Length(max: 40)]
        public string $numeroLot = '',

        #[Assert\NotBlank(message: 'La date de péremption est obligatoire.')]
        #[Assert\Date(message: 'Date de péremption invalide (format AAAA-MM-JJ).')]
        public string $datePeremption = '',
    ) {
    }
}
