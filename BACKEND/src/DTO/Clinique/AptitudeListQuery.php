<?php

namespace App\DTO\Clinique;

use Symfony\Component\Validator\Constraints as Assert;

final class AptitudeListQuery
{
    public function __construct(
        #[Assert\Positive]
        public int $page = 1,

        #[Assert\Range(min: 1, max: 100)]
        public int $limit = 10,

        #[Assert\Length(max: 100)]
        public ?string $search = null,

        #[Assert\Range(min: 2000, max: 2100)]
        public ?int $annee = null,

        #[Assert\Length(max: 20)]
        public ?string $statut = null,

        #[Assert\Length(max: 20)]
        public ?string $verdict = null,

        #[Assert\Length(max: 20)]
        public ?string $motif = null,

        #[Assert\Positive]
        public ?int $serviceId = null,
    ) {
        if ('' === $this->search) {
            $this->search = null;
        }
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
