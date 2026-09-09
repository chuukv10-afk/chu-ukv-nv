<?php

namespace App\DTO\Intendance;

use Symfony\Component\Validator\Constraints as Assert;

final class CreateGroupeBienInput
{
    public function __construct(
        #[Assert\Range(min: 2, max: 200, notInRangeMessage: 'Le nombre de copies doit être entre 2 et 200.')]
        public int $copies = 2,

        #[Assert\Positive(message: 'Le type est obligatoire.')]
        public int $typeId = 0,

        #[Assert\Positive(message: 'Le service est obligatoire.')]
        public int $serviceId = 0,

        public ?int $localId = null,

        /** @var list<string> */
        public array $codes = [],

        #[Assert\Length(max: 150)]
        public ?string $precision = null,

        #[Assert\Length(max: 100)]
        public ?string $marque = null,

        #[Assert\Length(max: 100)]
        public ?string $modele = null,

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
