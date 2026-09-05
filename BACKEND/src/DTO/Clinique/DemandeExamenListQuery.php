<?php

namespace App\DTO\Clinique;

use Symfony\Component\Validator\Constraints as Assert;

final class DemandeExamenListQuery
{
    public function __construct(
        #[Assert\Positive]
        public int $page = 1,

        #[Assert\Range(min: 1, max: 200)]
        public int $limit = 50,

        #[Assert\Length(max: 120)]
        public ?string $search = null,

        #[Assert\Length(max: 20)]
        public ?string $statut = null,

        #[Assert\Positive]
        public ?int $typeExamenId = null,
    ) {
    }
}
