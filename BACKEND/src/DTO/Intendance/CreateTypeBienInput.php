<?php

namespace App\DTO\Intendance;

use Symfony\Component\Validator\Constraints as Assert;

final class CreateTypeBienInput
{
    public function __construct(
        #[Assert\NotBlank(message: 'Le code est obligatoire.')]
        #[Assert\Length(max: 20)]
        public string $code = '',

        #[Assert\NotBlank(message: 'Le libellé est obligatoire.')]
        #[Assert\Length(max: 120)]
        public string $libelle = '',

        #[Assert\Positive(message: 'La famille est obligatoire.')]
        public int $familleId = 0,

        #[Assert\PositiveOrZero]
        public int $ordre = 0,

        public string $statut = 'ACTIF',
    ) {
    }
}
