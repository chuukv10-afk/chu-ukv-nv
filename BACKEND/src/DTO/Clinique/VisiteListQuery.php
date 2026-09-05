<?php

namespace App\DTO\Clinique;

use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

final class VisiteListQuery
{
    public function __construct(
        #[Assert\Positive]
        public int $page = 1,

        #[Assert\Range(min: 1, max: 100)]
        public int $limit = 10,

        #[Assert\Length(max: 100)]
        public ?string $search = null,

        #[Assert\Length(max: 20)]
        public ?string $statut = null,

        #[Assert\Positive]
        public ?int $serviceId = null,

        #[Assert\Positive]
        public ?int $dpiId = null,

        #[Assert\Uuid]
        public ?string $patientId = null,

        #[Assert\Date]
        public ?string $enterFrom = null,

        #[Assert\Date]
        public ?string $enterTo = null,

        public ?string $pendingHospitalization = null,
    ) {
        if ('' === $this->search) {
            $this->search = null;
        }

        if ('' === $this->statut) {
            $this->statut = null;
        }

        if ('' === $this->patientId) {
            $this->patientId = null;
        }

        if ('' === $this->enterFrom) {
            $this->enterFrom = null;
        }

        if ('' === $this->enterTo) {
            $this->enterTo = null;
        }

        if ('' === $this->pendingHospitalization) {
            $this->pendingHospitalization = null;
        }
    }

    public function wantsPendingHospitalization(): bool
    {
        if (null === $this->pendingHospitalization) {
            return false;
        }

        return in_array(strtolower(trim($this->pendingHospitalization)), ['1', 'true', 'oui', 'yes'], true);
    }

    #[Assert\Callback]
    public function validateDateRange(ExecutionContextInterface $context): void
    {
        if (null === $this->enterFrom || null === $this->enterTo) {
            return;
        }

        $from = \DateTimeImmutable::createFromFormat('Y-m-d', $this->enterFrom);
        $to = \DateTimeImmutable::createFromFormat('Y-m-d', $this->enterTo);

        if (false === $from || false === $to) {
            return;
        }

        if ($from > $to) {
            $context->buildViolation('La date de début doit être antérieure ou égale à la date de fin.')
                ->atPath('enterTo')
                ->addViolation();
        }
    }
}

