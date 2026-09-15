<?php

namespace App\DTO\Pharmacie;

use Symfony\Component\Validator\Constraints as Assert;

final class CompterInventaireProduitInput
{
    /**
     * @param list<CompterInventaireLigneSaisieInput> $lignes
     */
    public function __construct(
        #[Assert\Valid]
        public array $lignes = [],
    ) {
    }
}
