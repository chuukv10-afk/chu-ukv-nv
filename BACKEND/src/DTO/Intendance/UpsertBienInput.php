<?php

namespace App\DTO\Intendance;

use Symfony\Component\Validator\Constraints as Assert;

final class UpsertBienInput
{
    public function __construct(
        #[Assert\Positive(message: 'Le type est obligatoire.')]
        public int $typeId = 0,

        #[Assert\Positive(message: 'Le service est obligatoire.')]
        public int $serviceId = 0,

        public ?int $localId = null,

        #[Assert\Length(max: 40)]
        public ?string $codeInventaire = null,

        #[Assert\Length(max: 150)]
        public ?string $precision = null,

        #[Assert\Length(max: 100)]
        public ?string $marque = null,

        #[Assert\Length(max: 100)]
        public ?string $modele = null,

        #[Assert\Length(max: 80)]
        public ?string $numeroSerie = null,

        #[Assert\Length(max: 150)]
        public ?string $complementLocalisation = null,

        #[Assert\Length(max: 8)]
        public string $etat = 'F',

        public ?string $dateAcquisition = null,

        #[Assert\Length(max: 500)]
        public ?string $observation = null,
    ) {
    }
}
