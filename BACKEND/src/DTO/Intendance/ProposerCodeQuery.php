<?php

namespace App\DTO\Intendance;

use Symfony\Component\Validator\Constraints as Assert;

final class ProposerCodeQuery
{
    public function __construct(
        #[Assert\Positive(message: 'Le service est obligatoire.')]
        public int $serviceId = 0,

        #[Assert\Positive(message: 'Le type est obligatoire.')]
        public int $typeId = 0,

        #[Assert\Range(min: 1, max: 200)]
        public int $count = 1,
    ) {
    }
}
