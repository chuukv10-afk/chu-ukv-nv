<?php

namespace App\DTO\Clinique;

use Symfony\Component\Validator\Constraints as Assert;

final class MaladieListQuery
{
    public function __construct(
        #[Assert\Positive]
        public int $page = 1,

        #[Assert\Range(min: 1, max: 100)]
        public int $limit = 10,

        #[Assert\Length(max: 100)]
        public ?string $search = null,

        #[Assert\Length(max: 20)]
        public ?string $chapitre = null,
    ) {
        if ('' === $this->search) {
            $this->search = null;
        }

        if ('' === $this->chapitre) {
            $this->chapitre = null;
        }
    }
}
