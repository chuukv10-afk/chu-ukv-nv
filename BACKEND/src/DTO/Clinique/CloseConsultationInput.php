<?php

namespace App\DTO\Clinique;

use App\Entity\Consultation;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

final class CloseConsultationInput
{
    public function __construct(
        public bool $needsHospitalization = false,

        public bool $dischargePatient = false,

        public bool $wantsAppointment = false,

        public ?string $nextAppointmentAt = null,

        #[Assert\Length(max: 20)]
        public ?string $hospitalizationPatientOpinion = null,

        public ?string $hospitalizationObservation = null,
    ) {
    }

    #[Assert\Callback]
    public function validateBusinessRules(ExecutionContextInterface $context): void
    {
        if (!$this->needsHospitalization && $this->wantsAppointment) {
            if (null === $this->nextAppointmentAt || '' === trim($this->nextAppointmentAt)) {
                $context->buildViolation('La date du rendez-vous est obligatoire.')
                    ->atPath('nextAppointmentAt')
                    ->addViolation();
            } elseif (false === \DateTimeImmutable::createFromFormat(\DateTimeInterface::ATOM, trim($this->nextAppointmentAt))) {
                $context->buildViolation('La date du rendez-vous doit être au format ISO 8601.')
                    ->atPath('nextAppointmentAt')
                    ->addViolation();
            }
        }

        if ($this->needsHospitalization) {
            if (null === $this->hospitalizationPatientOpinion || '' === trim($this->hospitalizationPatientOpinion)) {
                $context->buildViolation('L\'avis du patient sur l\'hospitalisation est obligatoire.')
                    ->atPath('hospitalizationPatientOpinion')
                    ->addViolation();
            } elseif (!Consultation::isValidHospitalizationOpinion($this->hospitalizationPatientOpinion)) {
                $context->buildViolation('Avis d\'hospitalisation invalide.')
                    ->atPath('hospitalizationPatientOpinion')
                    ->addViolation();
            }

            if (Consultation::OPINION_NON_FAVORABLE === strtoupper(trim((string) $this->hospitalizationPatientOpinion))
                && (null === $this->hospitalizationObservation || '' === trim($this->hospitalizationObservation))) {
                $context->buildViolation('Une observation est requise lorsque l\'avis est non favorable.')
                    ->atPath('hospitalizationObservation')
                    ->addViolation();
            }
        }
    }
}
