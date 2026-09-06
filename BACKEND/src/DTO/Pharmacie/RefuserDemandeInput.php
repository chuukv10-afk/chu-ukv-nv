<?php

namespace App\DTO\Pharmacie;

use Symfony\Component\Validator\Constraints as Assert;

final class RefuserDemandeInput
{
    public function __construct(
        #[Assert\NotBlank(message: 'Le motif de refus est obligatoire.')]
        #[Assert\Length(max: 255)]
        public string $motif = '',
    ) {
    }
}
