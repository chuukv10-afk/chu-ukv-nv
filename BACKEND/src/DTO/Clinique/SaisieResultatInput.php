<?php

namespace App\DTO\Clinique;

use App\Entity\DemandeExamen;
use Symfony\Component\Validator\Constraints as Assert;

final class SaisieResultatInput
{
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Length(max: DemandeExamen::RESULTAT_MAX_LENGTH)]
        public ?string $resultat = null,

        #[Assert\Length(max: 255)]
        public ?string $fichier = null,
    ) {
    }
}
