<?php

namespace App\DTO\Clinique;

use Symfony\Component\Validator\Constraints as Assert;

final class CreateVisiteMesurePriseInput
{
    /**
     * @param list<array<string, mixed>> $mesures
     */
    public function __construct(
        #[Assert\Count(min: 1, max: 50)]
        public array $mesures = [],
    ) {
    }
}
