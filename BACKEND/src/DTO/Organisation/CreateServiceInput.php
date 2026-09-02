<?php

namespace App\DTO\Organisation;

use Symfony\Component\Validator\Constraints as Assert;

final class CreateServiceInput
{
    public function __construct(
        #[Assert\NotBlank(message: 'Le code du service est obligatoire.')]
        #[Assert\Length(max: 8, maxMessage: 'Le code ne peut pas dépasser {{ limit }} caractères.')]
        public string $code = '',

        #[Assert\NotBlank(message: 'Le libellé du service est obligatoire.')]
        #[Assert\Length(max: 100, maxMessage: 'Le libellé ne peut pas dépasser {{ limit }} caractères.')]
        public string $libelle = '',

        #[Assert\NotBlank(message: 'Le département est obligatoire.')]
        #[Assert\Positive(message: 'L\'identifiant du département doit être un entier positif.')]
        public int $departementId = 0,
    ) {
    }
}
