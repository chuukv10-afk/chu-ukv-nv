<?php

namespace App\DTO\Intendance;

use Symfony\Component\Validator\Constraints as Assert;

final class TransfererBienInput
{
    public function __construct(
        #[Assert\Positive(message: 'Le service destination est obligatoire.')]
        public int $serviceId = 0,

        public ?int $localId = null,

        #[Assert\NotBlank(message: 'Le motif du transfert est obligatoire.')]
        #[Assert\Length(max: 500)]
        public string $motif = '',
    ) {
    }
}
