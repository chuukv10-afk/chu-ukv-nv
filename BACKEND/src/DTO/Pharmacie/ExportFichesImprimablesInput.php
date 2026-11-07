<?php

namespace App\DTO\Pharmacie;

use Symfony\Component\Validator\Constraints as Assert;

final class ExportFichesImprimablesInput
{
    /**
     * @param list<int> $medicamentIds
     */
    public function __construct(
        #[Assert\Regex(pattern: '/^\d{4}-\d{2}-\d{2}$/', message: 'Date de début invalide (AAAA-MM-JJ).')]
        public ?string $dateFrom = null,

        #[Assert\Regex(pattern: '/^\d{4}-\d{2}-\d{2}$/', message: 'Date de fin invalide (AAAA-MM-JJ).')]
        public ?string $dateTo = null,

        #[Assert\Positive]
        public ?int $partie = null,

        #[Assert\Positive]
        public ?int $parties = null,

        #[Assert\Count(
            min: 1,
            minMessage: 'Sélectionnez au moins un médicament.',
        )]
        #[Assert\All([
            new Assert\Type('integer'),
            new Assert\Positive(),
        ])]
        public array $medicamentIds = [],
    ) {
        if ('' === $this->dateFrom) {
            $this->dateFrom = null;
        }
        if ('' === $this->dateTo) {
            $this->dateTo = null;
        }
        $this->medicamentIds = array_values(array_unique(array_map(static fn (mixed $id): int => (int) $id, $this->medicamentIds)));
    }
}
