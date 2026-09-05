<?php

namespace App\DTO\Referentiel;

use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

final class UpdateSigneVitalInput
{
    public function __construct(
        #[Assert\NotBlank(message: 'Le libellé est obligatoire.')]
        #[Assert\Length(max: 100)]
        public string $libelle = '',

        #[Assert\Length(max: 20)]
        public ?string $unite = null,

        public bool $demandeAuTriage = false,

        public bool $obligatoireAuTriage = false,

        #[Assert\PositiveOrZero]
        public int $ordre = 0,

        #[Assert\NotBlank]
        public string $statut = 'ACTIF',
    ) {
    }

    #[Assert\Callback]
    public function validateBusinessRules(ExecutionContextInterface $context): void
    {
        if ($this->obligatoireAuTriage && !$this->demandeAuTriage) {
            $context->buildViolation('Un signe vital obligatoire au triage doit d\'abord être demandé au triage.')
                ->atPath('obligatoireAuTriage')
                ->addViolation();
        }
    }
}
