<?php

namespace App\DTO\Intendance;

use Symfony\Component\Validator\Constraints as Assert;

final class CreateLocalInput
{
    public function __construct(
        #[Assert\Positive(message: 'Le service est obligatoire.')]
        public int $serviceId = 0,

        #[Assert\NotBlank(message: 'Le code est obligatoire.')]
        #[Assert\Length(max: 20)]
        public string $code = '',

        #[Assert\NotBlank(message: 'Le libellé est obligatoire.')]
        #[Assert\Length(max: 120)]
        public string $libelle = '',

        #[Assert\PositiveOrZero]
        public int $ordre = 0,

        public string $statut = 'ACTIF',
    ) {
    }
}
