<?php

namespace App\DTO\Imagerie;

use App\Entity\EtudeImagerie;
use Symfony\Component\Validator\Constraints as Assert;

final class ImagerieListQuery
{
    public function __construct(
        #[Assert\Positive]
        public int $page = 1,

        #[Assert\Range(min: 1, max: 100)]
        public int $limit = 10,

        #[Assert\Length(max: 100)]
        public ?string $search = null,

        #[Assert\Length(max: 20)]
        public ?string $statut = null,

        public ?string $patientId = null,

        #[Assert\Length(max: 80)]
        public ?string $statuts = null,

        #[Assert\Choice(choices: [EtudeImagerie::SOURCE_INTERNE, EtudeImagerie::SOURCE_EXTERNE])]
        public ?string $source = null,

        #[Assert\Choice(choices: ['AUJOURDHUI', 'SEMAINE', 'MOIS', 'ANNEE', 'PERSONNALISE'])]
        public ?string $periode = null,

        public ?string $dateFrom = null,

        public ?string $dateTo = null,
    ) {
        if ('' === $this->search) {
            $this->search = null;
        }
        if ('' === $this->statut) {
            $this->statut = null;
        }
        if ('' === $this->patientId) {
            $this->patientId = null;
        }
        if ('' === $this->statuts) {
            $this->statuts = null;
        }
        if ('' === $this->source) {
            $this->source = null;
        }
        if ('' === $this->periode) {
            $this->periode = null;
        }
        if ('' === $this->dateFrom) {
            $this->dateFrom = null;
        }
        if ('' === $this->dateTo) {
            $this->dateTo = null;
        }
    }
}
