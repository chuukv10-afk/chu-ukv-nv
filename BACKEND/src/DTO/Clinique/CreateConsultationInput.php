<?php

namespace App\DTO\Clinique;

use App\Entity\Consultation;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

final class CreateConsultationInput
{
    public function __construct(
        #[Assert\NotNull(message: 'La visite est obligatoire.')]
        #[Assert\Positive]
        public ?int $visiteId = null,

        #[Assert\Length(max: 20)]
        public ?string $typeConsultation = null,

        #[Assert\Length(max: 255)]
        public ?string $motif = null,

        #[Assert\Length(max: 20)]
        public ?string $statut = null,
    ) {
    }

    #[Assert\Callback]
    public function validateBusinessRules(ExecutionContextInterface $context): void
    {
        if (null !== $this->typeConsultation && '' !== trim($this->typeConsultation) && !Consultation::isValidCreatableType($this->typeConsultation)) {
            $context->buildViolation('Type de consultation invalide.')
                ->atPath('typeConsultation')
                ->addViolation();
        }

        if (null !== $this->statut && '' !== trim($this->statut) && !Consultation::isValidCreatableStatut($this->statut)) {
            $context->buildViolation('Statut initial invalide (PLANIFIEE ou EN_COURS attendu).')
                ->atPath('statut')
                ->addViolation();
        }
    }
}
