<?php

namespace App\DTO\Referentiel;

use Symfony\Component\Validator\Constraints as Assert;

final class UpdatePlainteInput
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
