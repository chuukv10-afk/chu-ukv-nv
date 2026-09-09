<?php

namespace App\DTO\Intendance;

use Symfony\Component\Validator\Constraints as Assert;

final class UpdateFamilleBienInput
{
    public function __construct(
        #[Assert\NotBlank(message: 'Le libellé est obligatoire.')]
        #[Assert\Length(max: 100)]
        public string $libelle = '',

        #[Assert\PositiveOrZero]
        public int $ordre = 0,

        public string $statut = 'ACTIF',
    ) {
    }
}
