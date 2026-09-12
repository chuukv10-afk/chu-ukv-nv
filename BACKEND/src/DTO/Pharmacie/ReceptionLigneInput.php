<?php

namespace App\DTO\Pharmacie;

use Symfony\Component\Validator\Constraints as Assert;

final class ReceptionLigneInput
{
    public function __construct(
        #[Assert\Positive(message: 'Le médicament est obligatoire.')]
        public int $medicamentId = 0,

        #[Assert\NotBlank(message: 'Le n° de lot est obligatoire.')]
        #[Assert\Length(max: 40)]
        public string $numeroLot = '',

        #[Assert\NotBlank(message: 'La date de péremption est obligatoire.')]
        public string $datePeremption = '',

        #[Assert\Positive(message: 'La quantité doit être supérieure à 0.')]
        public int $quantite = 0,

        #[Assert\NotBlank(message: 'Le prix d\'achat est obligatoire.')]
        public string $prixAchatUnitaire = '0',

        public ?string $prixVente = null,
    ) {
        $normalized = UpsertVenteInput::toDateOnly($this->datePeremption);
        if (null !== $normalized) {
            $this->datePeremption = $normalized;
        }
    }
}
