<?php

namespace App\DTO\Patient;

use App\Entity\CategorieTarifaire;
use App\Entity\Patient;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

final class CreatePatientInput
{
    public function __construct(
        #[Assert\NotBlank(message: 'Le nom est obligatoire.')]
        #[Assert\Length(max: 50)]
        public string $nom = '',

        #[Assert\NotBlank(message: 'Le post-nom est obligatoire.')]
        #[Assert\Length(max: 50)]
        public string $postNom = '',

        #[Assert\Length(max: 50)]
        public ?string $prenom = null,

        #[Assert\Length(max: 15)]
        public ?string $telephone = null,

        #[Assert\Length(max: 100)]
        public ?string $adresse = null,

        #[Assert\Length(max: 30)]
        public ?string $lieuNaissance = null,

        public ?string $dateNaissance = null,

        #[Assert\NotBlank(message: 'Le sexe est obligatoire.')]
        #[Assert\Choice(choices: ['M', 'F'], message: 'Le sexe doit être M ou F.')]
        public string $sexe = '',

        #[Assert\Length(max: 20)]
        public ?string $groupeSanguin = null,

        #[Assert\Length(max: 50)]
        public ?string $personneAprevenir = null,

        #[Assert\Length(max: 20)]
        public ?string $contactAPrevenir = null,

        #[Assert\NotBlank(message: 'La catégorie tarifaire est obligatoire.')]
        #[Assert\Choice(callback: [CategorieTarifaire::class, 'codes'], message: 'Catégorie tarifaire invalide.')]
        public string $categorieTarifaire = '',

        #[Assert\Positive]
        public ?int $structureId = null,

        #[Assert\Length(max: 50)]
        public ?string $numeroAffiliation = null,

        #[Assert\Length(max: 20)]
        public ?string $codeUkv = null,

        #[Assert\Positive]
        public ?int $filiereId = null,

        #[Assert\Positive]
        public ?int $organisationId = null,

        public string $status = Patient::STATUS_ACTIF,
    ) {
        if ('' === $this->dateNaissance) {
            $this->dateNaissance = null;
        }
    }

    #[Assert\Callback]
    public function validateBusinessRules(ExecutionContextInterface $context): void
    {
        if (!Patient::isValidStatus($this->status)) {
            $context->buildViolation('Statut patient invalide.')
                ->atPath('status')
                ->addViolation();
        }

        if (null !== $this->dateNaissance && '' !== trim($this->dateNaissance) && false === \DateTimeImmutable::createFromFormat('Y-m-d', $this->dateNaissance)) {
            $context->buildViolation('La date de naissance doit être au format AAAA-MM-JJ.')
                ->atPath('dateNaissance')
                ->addViolation();
        }
    }
}
