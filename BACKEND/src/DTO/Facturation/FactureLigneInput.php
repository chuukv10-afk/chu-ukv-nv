<?php

namespace App\DTO\Facturation;

use Symfony\Component\Validator\Constraints as Assert;

final class FactureLigneInput
{
    public function __construct(
        #[Assert\Positive(message: 'L\'acte est obligatoire.')]
        public int $acteId = 0,

        #[Assert\Positive(message: 'Le service facturant est obligatoire.')]
        public int $serviceId = 0,

        #[Assert\Positive(message: 'La quantité doit être supérieure à 0.')]
        public int $quantite = 1,

        #[Assert\Length(max: 16)]
        public string $remiseType = 'NONE',

        public string $remiseValeur = '0',

        /** Prix unitaire de cette facture. Vide = tarif de la grille. N'écrit jamais dans la grille. */
        #[Assert\Length(max: 20)]
        public ?string $tarifUnitaire = null,
    ) {
        if ('' === trim((string) $this->tarifUnitaire)) {
            $this->tarifUnitaire = null;
        }
    }
}
