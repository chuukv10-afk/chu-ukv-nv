<?php

namespace App\DTO\Intendance;

use Symfony\Component\Validator\Constraints as Assert;

final class BienListQuery
{
    public function __construct(
        #[Assert\Positive]
        public int $page = 1,

        #[Assert\Range(min: 1, max: 200)]
        public int $limit = 20,

        #[Assert\Length(max: 100)]
        public ?string $search = null,

        public ?int $serviceId = null,

        public ?int $localId = null,

        public bool $sansLocal = false,

        public ?int $typeId = null,

        public ?int $familleId = null,

        #[Assert\Length(max: 8)]
        public ?string $etat = null,

        public bool $includeReformes = false,
    ) {
        if ('' === $this->search) {
            $this->search = null;
        }
        if ('' === $this->etat) {
            $this->etat = null;
        }
    }
}
