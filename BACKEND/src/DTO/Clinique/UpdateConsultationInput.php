<?php

namespace App\DTO\Clinique;

use App\Entity\Consultation;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

final class UpdateConsultationInput
{
    /**
     * @param list<string>|null $complementAnamnese
     * @param array<string, mixed>|null $physicalExam
     * @param array<string, mixed>|null $evolutionSheet
     */
    public function __construct(
        #[Assert\Length(max: 20)]
        public ?string $typeConsultation = null,

        public ?string $motif = null,

        public ?string $histoireMaladie = null,

        public ?string $physicalExamText = null,

        public ?string $conduireATenir = null,

        public ?array $complementAnamnese = null,

        public ?array $physicalExam = null,

        public ?array $evolutionSheet = null,

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

        if (null !== $this->statut && '' !== trim($this->statut) && !Consultation::isValidStatut($this->statut)) {
            $context->buildViolation('Statut de consultation invalide.')
                ->atPath('statut')
                ->addViolation();
        }

        if (Consultation::STATUT_TERMINEE === Consultation::normalizeStatut((string) $this->statut)) {
            $context->buildViolation('Utilisez la clôture enrichie pour terminer une consultation.')
                ->atPath('statut')
                ->addViolation();
        }

        if (null !== $this->complementAnamnese) {
            foreach ($this->complementAnamnese as $index => $item) {
                if (!is_string($item) || '' === trim($item)) {
                    $context->buildViolation('Chaque élément du complément d\'anamnèse doit être un texte non vide.')
                        ->atPath(sprintf('complementAnamnese[%d]', $index))
                        ->addViolation();
                }
            }
        }

        $hasChange = null !== $this->typeConsultation
            || null !== $this->motif
            || null !== $this->histoireMaladie
            || null !== $this->physicalExamText
            || null !== $this->conduireATenir
            || null !== $this->complementAnamnese
            || null !== $this->physicalExam
            || null !== $this->evolutionSheet
            || (null !== $this->statut && '' !== trim((string) $this->statut));

        if (!$hasChange) {
            $context->buildViolation('Aucune modification fournie.')
                ->addViolation();
        }
    }
}
