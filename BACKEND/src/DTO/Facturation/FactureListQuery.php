<?php

namespace App\DTO\Facturation;

use Symfony\Component\Validator\Constraints as Assert;

final class FactureListQuery
{
    public function __construct(
        #[Assert\Positive]
        public int $page = 1,

        #[Assert\Range(min: 1, max: 100)]
        public int $limit = 10,

        #[Assert\Length(max: 100)]
        public ?string $search = null,

        #[Assert\Length(max: 16)]
        public ?string $statut = null,

        #[Assert\Length(max: 8)]
        public ?string $categorieTarifaire = null,

        public ?int $structureId = null,

        #[Assert\Length(max: 16)]
        public ?string $dateFrom = null,

        #[Assert\Length(max: 16)]
        public ?string $dateTo = null,
    ) {
        if ('' === $this->search) {
            $this->search = null;
        }
        if ('' === $this->statut) {
            $this->statut = null;
        }
        if ('' === $this->categorieTarifaire) {
            $this->categorieTarifaire = null;
        }
        if (0 === $this->structureId) {
            $this->structureId = null;
        }
        if ('' === $this->dateFrom) {
            $this->dateFrom = null;
        }
        if ('' === $this->dateTo) {
            $this->dateTo = null;
        }
    }
}
