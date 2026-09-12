<?php

namespace App\DTO\Clinique;

use Symfony\Component\Validator\Constraints as Assert;

final class AptitudeStatsQuery
{
    public function __construct(
        #[Assert\Range(min: 2000, max: 2100)]
        public ?int $annee = null,

        #[Assert\Length(max: 20)]
        public ?string $statut = null,

        #[Assert\Length(max: 20)]
        public ?string $verdict = null,

        #[Assert\Length(max: 20)]
        public ?string $motif = null,
    ) {
        if ('' === $this->statut) {
            $this->statut = null;
        }
        if ('' === $this->verdict) {
            $this->verdict = null;
        }
        if ('' === $this->motif) {
            $this->motif = null;
        }
    }
}
