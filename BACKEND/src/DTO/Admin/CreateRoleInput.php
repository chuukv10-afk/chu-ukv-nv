<?php

namespace App\DTO\Admin;

use App\Entity\PersonnelRole;
use Symfony\Component\Validator\Constraints as Assert;

final class CreateRoleInput
{
    public function __construct(
        #[Assert\NotBlank(message: 'Le code du rôle est obligatoire.')]
        #[Assert\Length(max: 20, maxMessage: 'Le code ne peut pas dépasser {{ limit }} caractères.')]
        #[Assert\Regex(
            pattern: '/^[A-Za-z0-9_]+$/',
            message: 'Le code ne peut contenir que des lettres, chiffres et underscores.',
        )]
        public string $code = '',

        #[Assert\NotBlank(message: 'Le libellé du rôle est obligatoire.')]
        #[Assert\Length(max: 100, maxMessage: 'Le libellé ne peut pas dépasser {{ limit }} caractères.')]
        public string $libelle = '',

        #[Assert\NotBlank(message: 'Le périmètre du rôle est obligatoire.')]
        #[Assert\Choice(
            choices: [
                PersonnelRole::PERIMETRE_GLOBAL,
                PersonnelRole::PERIMETRE_DEPARTEMENT,
                PersonnelRole::PERIMETRE_SERVICE,
            ],
            message: 'Le périmètre doit être GLOBAL, DEPARTEMENT ou SERVICE.',
        )]
        public string $perimetre = '',
    ) {
    }
}
