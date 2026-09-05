<?php

namespace App\DTO\Admin;

use App\Entity\Personnel;
use Symfony\Component\Validator\Constraints as Assert;

final class CreatePersonnelInput
{
    /**
     * @param list<int> $specialiteIds
     * @param list<PersonnelRoleAssignmentInput> $roleAssignments
     */
    public function __construct(
        #[Assert\NotBlank(message: 'Le nom est obligatoire.')]
        #[Assert\Length(max: 50)]
        public string $nom = '',

        #[Assert\Length(max: 50)]
        public ?string $postNom = null,

        #[Assert\Length(max: 50)]
        public ?string $prenom = null,

        #[Assert\NotBlank(message: 'Le téléphone est obligatoire.')]
        #[Assert\Length(max: 15)]
        public string $telephone = '',

        #[Assert\NotBlank(message: 'Le matricule est obligatoire.')]
        #[Assert\Length(max: 20)]
        public string $matricule = '',

        #[Assert\NotBlank(message: 'Le sexe est obligatoire.')]
        #[Assert\Choice(choices: ['M', 'F'], message: 'Le sexe doit être M ou F.')]
        public string $sexe = '',

        #[Assert\NotBlank(message: 'Le type est obligatoire.')]
        public string $type = '',

        #[Assert\NotBlank(message: 'Le statut est obligatoire.')]
        public string $status = Personnel::STATUS_ACTIF,

        #[Assert\NotBlank(message: 'Le mot de passe est obligatoire.')]
        #[Assert\Length(min: 6, max: 255)]
        public string $password = '',

        #[Assert\Length(max: 100)]
        public ?string $adresse = null,

        #[Assert\Length(max: 50)]
        public ?string $lieuNaissance = null,

        #[Assert\Length(max: 20)]
        public ?string $cnome = null,

        #[Assert\Positive]
        public ?int $gradeId = null,

        #[Assert\Positive]
        public ?int $serviceId = null,

        #[Assert\Type(type: 'array')]
        public array $specialiteIds = [],

        #[Assert\Type(type: 'array')]
        #[Assert\Valid]
        public array $roleAssignments = [],
    ) {
    }

    #[Assert\Callback]
    public function validateBusinessRules(\Symfony\Component\Validator\Context\ExecutionContextInterface $context): void
    {
        if (!Personnel::isValidType($this->type)) {
            $context->buildViolation('Type de personnel invalide.')
                ->atPath('type')
                ->addViolation();
        }

        if (!Personnel::isValidStatus($this->status)) {
            $context->buildViolation('Statut invalide.')
                ->atPath('status')
                ->addViolation();
        }
    }
}
