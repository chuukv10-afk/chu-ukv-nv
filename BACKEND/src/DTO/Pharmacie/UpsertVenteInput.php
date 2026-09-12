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

        #[Assert\Length(max: 10)]
        public ?string $dateVente = null,
    ) {
        $this->dateVente = self::toDateOnly($this->dateVente);
    }

    public static function toDateOnly(?string $value): ?string
    {
        if (null === $value) {
            return null;
        }
        $value = trim($value);
        if ('' === $value) {
            return null;
        }
        if (preg_match('/^(\d{4}-\d{2}-\d{2})/', $value, $matches)) {
            return $matches[1];
        }

        try {
            return (new \DateTimeImmutable($value))->format('Y-m-d');
        } catch (\Exception) {
            return $value;
        }
    }
}
