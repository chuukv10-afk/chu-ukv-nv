<?php

namespace App\DTO\Pharmacie;

use Symfony\Component\Validator\Constraints as Assert;

final class UpsertDemandeServiceInput
{
    public function __construct(
        #[Assert\Positive(message: 'Le service est obligatoire.')]
        public int $serviceId = 0,

        #[Assert\Length(max: 255)]
        public ?string $motif = null,

        #[Assert\Positive]
        public ?int $visiteId = null,

        /** @var list<DemandeServiceLigneInput> */
        #[Assert\Valid]
        #[Assert\Count(min: 1, minMessage: 'Ajoutez au moins une ligne.')]
        public array $lignes = [],
    ) {
    }
}
