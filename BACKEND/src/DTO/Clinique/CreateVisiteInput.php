<?php

namespace App\DTO\Clinique;

use App\Entity\Triage;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

final class CreateVisiteInput
{
    /**
     * @param list<TriageSigneVitalInput> $signesVitaux
     */
    public function __construct(
        #[Assert\Positive(message: 'Le dossier patient (DPI) est obligatoire.')]
        public int $dpiId = 0,

        #[Assert\Positive(message: 'Le service est obligatoire.')]
        public int $serviceId = 0,

        #[Assert\NotBlank(message: 'Le type d\'entrée est obligatoire.')]
        public string $typeEntree = Triage::TYPE_CONSULTATION,

        #[Assert\NotBlank(message: 'Le motif de venue est obligatoire.')]
        #[Assert\Length(max: 255)]
        public string $motif = '',

        #[Assert\Range(min: 1, max: 5)]
        public ?int $priorite = null,

        #[Assert\Type(type: 'array')]
        #[Assert\Valid]
        public array $signesVitaux = [],

        public ?string $sortedPrevuAt = null,
    ) {
    }

    #[Assert\Callback]
    public function validateBusinessRules(ExecutionContextInterface $context): void
    {
        if (!Triage::isValidTypeEntree($this->typeEntree)) {
            $context->buildViolation('Type d\'entrée invalide.')
                ->atPath('typeEntree')
                ->addViolation();
        }

        if (null !== $this->sortedPrevuAt && '' !== trim($this->sortedPrevuAt) && false === \DateTimeImmutable::createFromFormat(\DateTimeInterface::ATOM, $this->sortedPrevuAt)) {
            $context->buildViolation('La date de sortie prévue doit être au format ISO 8601.')
                ->atPath('sortedPrevuAt')
                ->addViolation();
        }
    }
}
