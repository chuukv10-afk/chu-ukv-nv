<?php

namespace App\DTO\Patient;

use App\Entity\Dpi;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

final class UpdateDpiInput
{
    public function __construct(
        #[Assert\NotBlank(message: 'Le statut du dossier est obligatoire.')]
        public string $statut = Dpi::STATUT_OUVERT,
    ) {
    }

    #[Assert\Callback]
    public function validateBusinessRules(ExecutionContextInterface $context): void
    {
        if (!Dpi::isValidStatut($this->statut)) {
            $context->buildViolation('Statut DPI invalide.')
                ->atPath('statut')
                ->addViolation();
        }
    }
}
