<?php

namespace App\DTO\Clinique;

use App\Entity\Visite;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

final class UpdateVisiteInput
{
    public function __construct(
        #[Assert\Positive]
        public ?int $serviceId = null,

        public ?string $statut = null,

        public ?int $litId = null,

        public ?string $sortedPrevuAt = null,

        public bool $dischargeHospitalization = false,
    ) {
    }

    #[Assert\Callback]
    public function validateBusinessRules(ExecutionContextInterface $context): void
    {
        if (null !== $this->statut && '' !== trim($this->statut) && !Visite::isValidStatut($this->statut)) {
            $context->buildViolation('Statut de visite invalide.')
                ->atPath('statut')
                ->addViolation();
        }

        if (null !== $this->sortedPrevuAt && '' !== trim($this->sortedPrevuAt) && false === \DateTimeImmutable::createFromFormat(\DateTimeInterface::ATOM, $this->sortedPrevuAt)) {
            $context->buildViolation('La date de sortie prévue doit être au format ISO 8601.')
                ->atPath('sortedPrevuAt')
                ->addViolation();
        }

        if (
            null === $this->serviceId
            && (null === $this->statut || '' === trim((string) $this->statut))
            && null === $this->litId
            && (null === $this->sortedPrevuAt || '' === trim((string) $this->sortedPrevuAt))
            && !$this->dischargeHospitalization
        ) {
            $context->buildViolation('Aucune modification fournie.')
                ->addViolation();
        }

        if ($this->dischargeHospitalization) {
            $targetStatut = null !== $this->statut && '' !== trim((string) $this->statut)
                ? Visite::normalizeStatut($this->statut)
                : null;
            if (Visite::STATUT_TERMINEE !== $targetStatut) {
                $context->buildViolation('La fin d\'hospitalisation doit cibler le statut TERMINEE.')
                    ->atPath('dischargeHospitalization')
                    ->addViolation();
            }
        }
    }
}
