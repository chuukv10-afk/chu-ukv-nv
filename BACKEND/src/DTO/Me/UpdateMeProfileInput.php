<?php

namespace App\DTO\Me;

use Symfony\Component\Validator\Constraints as Assert;

final class UpdateMeProfileInput
{
    public function __construct(
        #[Assert\NotBlank(message: 'Le nom est obligatoire.')]
        #[Assert\Length(max: 50)]
        public string $nom = '',

        #[Assert\Length(max: 50)]
        public ?string $postNom = null,

        #[Assert\Length(max: 50)]
        public ?string $prenom = null,

        #[Assert\NotBlank(message: 'Le sexe est obligatoire.')]
        #[Assert\Choice(choices: ['M', 'F'], message: 'Le sexe doit être M ou F.')]
        public string $sexe = 'M',

        #[Assert\Length(max: 100)]
        public ?string $adresse = null,

        #[Assert\Length(max: 50)]
        public ?string $lieuNaissance = null,
    ) {
        if ('' === $this->postNom) {
            $this->postNom = null;
        }
        if ('' === $this->prenom) {
            $this->prenom = null;
        }
        if ('' === $this->adresse) {
            $this->adresse = null;
        }
        if ('' === $this->lieuNaissance) {
            $this->lieuNaissance = null;
        }
    }
}
