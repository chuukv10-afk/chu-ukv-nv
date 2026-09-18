<?php

namespace App\DTO\Clinique;

use Symfony\Component\Validator\Constraints as Assert;

final class AptitudeIdsInput
{
    /**
     * @param list<int> $ids
     */
    public function __construct(
        #[Assert\NotBlank(message: 'Sélectionnez au moins un certificat.')]
        #[Assert\Count(min: 1, max: 80, minMessage: 'Sélectionnez au moins un certificat.', maxMessage: 'Maximum 80 certificats à la fois.')]
        public array $ids = [],
    ) {
    }
}
