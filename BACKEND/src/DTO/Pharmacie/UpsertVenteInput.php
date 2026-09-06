<?php

namespace App\DTO\Pharmacie;

use Symfony\Component\Validator\Constraints as Assert;

final class UpsertVenteInput
{
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Choice(choices: ['PATIENT', 'PASSANT'])]
        public string $clientType = 'PASSANT',

        public ?string $patientId = null,

        #[Assert\Length(max: 150)]
        public ?string $clientNom = null,

        public ?int $visiteId = null,

        #[Assert\NotBlank]
        #[Assert\Choice(choices: ['ESPECES', 'MOBILE'])]
        public string $modePaiement = 'ESPECES',

        /** @var list<VenteLigneInput> */
        #[Assert\Valid]
        #[Assert\Count(min: 1, minMessage: 'Ajoutez au moins une ligne.')]
        public array $lignes = [],
    ) {
    }
}
