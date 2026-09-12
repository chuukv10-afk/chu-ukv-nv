<?php

namespace App\DTO\Pharmacie;

use Symfony\Component\Validator\Constraints as Assert;

final class UpsertReceptionInput
{
    public function __construct(
        #[Assert\Positive(message: 'Le fournisseur est obligatoire.')]
        public int $fournisseurId = 0,

        #[Assert\NotBlank(message: 'La date de réception est obligatoire.')]
        public string $dateReception = '',

        #[Assert\Length(max: 80)]
        public ?string $referenceExterne = null,

        /** @var list<ReceptionLigneInput> */
        #[Assert\Valid]
        #[Assert\Count(min: 1, minMessage: 'Ajoutez au moins une ligne.')]
        public array $lignes = [],
    ) {
        $normalized = UpsertVenteInput::toDateOnly($this->dateReception);
        $this->dateReception = $normalized ?? $this->dateReception;
        if (is_array($this->lignes)) {
            foreach ($this->lignes as $ligne) {
                if (is_object($ligne) && isset($ligne->datePeremption)) {
                    $ligne->datePeremption = UpsertVenteInput::toDateOnly((string) $ligne->datePeremption) ?? $ligne->datePeremption;
                }
            }
        }
    }
}
