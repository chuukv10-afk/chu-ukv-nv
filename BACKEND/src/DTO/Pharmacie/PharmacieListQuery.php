<?php

namespace App\DTO\Pharmacie;

use Symfony\Component\Validator\Constraints as Assert;

final class PharmacieListQuery
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

        #[Assert\Regex(pattern: '/^\d{4}-\d{2}-\d{2}$/', message: 'Date de début invalide (AAAA-MM-JJ).')]
        public ?string $dateFrom = null,

        #[Assert\Regex(pattern: '/^\d{4}-\d{2}-\d{2}$/', message: 'Date de fin invalide (AAAA-MM-JJ).')]
        public ?string $dateTo = null,

        #[Assert\Length(max: 20)]
        public ?string $statutPaiement = null,

        #[Assert\Positive]
        public ?int $medicamentId = null,

        #[Assert\Length(max: 20)]
        public ?string $type = null,
    ) {
        if ('' === $this->search) {
            $this->search = null;
        }
        if ('' === $this->statut) {
            $this->statut = null;
        }
        if ('' === $this->dateFrom) {
            $this->dateFrom = null;
        }
        if ('' === $this->dateTo) {
            $this->dateTo = null;
        }
        if ('' === $this->statutPaiement) {
            $this->statutPaiement = null;
        }
        if ('' === $this->type) {
            $this->type = null;
        }
    }
}
