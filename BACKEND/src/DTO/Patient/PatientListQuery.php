<?php

namespace App\DTO\Patient;

use Symfony\Component\Validator\Constraints as Assert;

final class PatientListQuery
{
    public function __construct(
        #[Assert\Positive]
        public int $page = 1,

        #[Assert\Range(min: 1, max: 100)]
        public int $limit = 10,

        #[Assert\Length(max: 100)]
        public ?string $search = null,

        #[Assert\Length(max: 20)]
        public ?string $status = null,

        #[Assert\Length(max: 1)]
        public ?string $sexe = null,

        #[Assert\Positive]
        public ?int $filiereId = null,

        #[Assert\Positive]
        public ?int $organisationId = null,

        #[Assert\Length(max: 30)]
        public ?string $typeInstitution = null,
    ) {
        if ('' === $this->search) {
            $this->search = null;
        }

        if ('' === $this->status) {
            $this->status = null;
        }

        if ('' === $this->sexe) {
            $this->sexe = null;
        }

        if ('' === $this->typeInstitution) {
            $this->typeInstitution = null;
        }
    }
}
