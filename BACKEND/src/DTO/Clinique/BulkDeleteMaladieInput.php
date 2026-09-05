<?php

namespace App\DTO\Clinique;

use Symfony\Component\Validator\Constraints as Assert;

final class BulkDeleteMaladieInput
{
    /**
     * @param list<int> $ids
     */
    public function __construct(
        #[Assert\Count(min: 1, minMessage: 'Sélectionnez au moins une maladie à supprimer.')]
        #[Assert\All([
            new Assert\Type('integer'),
            new Assert\Positive(),
        ])]
        public array $ids = [],
    ) {
        $this->ids = array_values(array_unique(array_map('intval', $this->ids)));
    }
}
