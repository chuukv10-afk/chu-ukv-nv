<?php

namespace App\DTO\Referentiel;

use App\Entity\OrganisationPartenaire;
use Symfony\Component\Validator\Constraints as Assert;

final class UpdateOrganisationPartenaireInput
{
    public function __construct(
        #[Assert\NotBlank(message: 'Le libellé de l\'organisation est obligatoire.')]
        #[Assert\Length(max: 150, maxMessage: 'Le libellé ne peut pas dépasser {{ limit }} caractères.')]
        public string $libelle = '',

        #[Assert\NotBlank(message: 'Le type d\'institution est obligatoire.')]
        #[Assert\Choice(callback: [OrganisationPartenaire::class, 'getTypesInstitution'], message: 'Type d\'institution invalide.')]
        public string $typeInstitution = '',

        #[Assert\Choice(callback: [OrganisationPartenaire::class, 'getStatuts'], message: 'Statut invalide.')]
        public string $statut = OrganisationPartenaire::STATUT_ACTIF,
    ) {
    }
}
