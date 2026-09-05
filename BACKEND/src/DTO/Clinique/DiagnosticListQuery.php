<?php

namespace App\DTO\Clinique;

use Symfony\Component\Validator\Constraints as Assert;

final class DiagnosticListQuery
{
    public const SCOPE_CONSULTATION = 'consultation';
    public const SCOPE_VISITE = 'visite';

    public function __construct(
        #[Assert\Positive]
        public int $page = 1,

        #[Assert\Range(min: 1, max: 200)]
        public int $limit = 50,

        #[Assert\Choice(choices: [self::SCOPE_CONSULTATION, self::SCOPE_VISITE])]
        public string $scope = self::SCOPE_CONSULTATION,
    ) {
    }

    public function isVisiteScope(): bool
    {
        return self::SCOPE_VISITE === $this->scope;
    }
}
