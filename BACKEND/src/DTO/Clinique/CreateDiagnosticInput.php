<?php

namespace App\DTO\Clinique;

use Symfony\Component\Validator\Constraints as Assert;

final class CreateDiagnosticInput
{
    public function __construct(
        #[Assert\NotNull]
        #[Assert\Positive]
        public ?int $maladieId = null,

        #[Assert\Length(max: 20)]
        public ?string $type = null,

        #[Assert\Length(max: 20)]
        public ?string $certitude = null,

        #[Assert\Length(max: 255)]
        public ?string $remarque = null,
    ) {
    }
}
