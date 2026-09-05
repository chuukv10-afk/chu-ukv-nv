<?php

namespace App\DTO\Admin;

use App\Entity\Permission;
use Symfony\Component\Validator\Constraints as Assert;

final class UpdatePermissionInput
{
    public function __construct(
        #[Assert\NotBlank(message: 'Le code de la permission est obligatoire.')]
        #[Assert\Length(max: 50, maxMessage: 'Le code ne peut pas dépasser {{ limit }} caractères.')]
        #[Assert\Regex(
            pattern: '/^[a-z0-9_.]+$/',
            message: 'Le code ne peut contenir que des minuscules, chiffres, points et underscores.',
        )]
        public string $code = '',

        #[Assert\NotBlank(message: 'Le libellé de la permission est obligatoire.')]
        #[Assert\Length(max: 255, maxMessage: 'Le libellé ne peut pas dépasser {{ limit }} caractères.')]
        public string $libelle = '',

        #[Assert\Length(max: 300, maxMessage: 'La description ne peut pas dépasser {{ limit }} caractères.')]
        public ?string $description = null,

        #[Assert\NotBlank(message: 'Le module est obligatoire.')]
        #[Assert\Choice(
            choices: [
                Permission::MODULE_PATIENT,
                Permission::MODULE_CLINIQUE,
                Permission::MODULE_FACTURATION,
                Permission::MODULE_ORGANISATION,
                Permission::MODULE_REFERENTIEL,
                Permission::MODULE_ADMIN,
            ],
            message: 'Module invalide.',
        )]
        public string $module = '',
    ) {
    }
}
