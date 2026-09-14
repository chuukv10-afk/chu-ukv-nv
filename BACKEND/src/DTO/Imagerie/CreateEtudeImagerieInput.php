<?php

namespace App\DTO\Imagerie;

use Symfony\Component\Validator\Constraints as Assert;

final class CreateEtudeImagerieInput
{
    public function __construct(
        public ?string $patientId = null,

        #[Assert\Positive]
        public ?int $examenId = null,

        public ?int $demandeExamenId = null,

        #[Assert\Length(max: 4000)]
        public ?string $indication = null,
    ) {
        if ('' === $this->patientId) {
            $this->patientId = null;
        }
        if ('' === $this->indication) {
            $this->indication = null;
        }
    }
}
