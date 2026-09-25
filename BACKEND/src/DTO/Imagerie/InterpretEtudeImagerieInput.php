<?php

namespace App\DTO\Imagerie;

use Symfony\Component\Validator\Constraints as Assert;

final class InterpretEtudeImagerieInput
{
    public function __construct(
        #[Assert\Length(max: 12000)]
        public ?string $resultat = null,

        #[Assert\Length(max: 8000)]
        public ?string $constatations = null,
    ) {
    }

    public function texte(): string
    {
        return trim((string) ('' !== trim((string) $this->resultat) ? $this->resultat : $this->constatations));
    }
}
