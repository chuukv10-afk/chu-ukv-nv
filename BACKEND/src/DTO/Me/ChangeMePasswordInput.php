<?php

namespace App\DTO\Me;

use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

final class ChangeMePasswordInput
{
    public function __construct(
        #[Assert\NotBlank(message: 'Le mot de passe actuel est obligatoire.')]
        public string $currentPassword = '',

        #[Assert\NotBlank(message: 'Le nouveau mot de passe est obligatoire.')]
        #[Assert\Length(min: 6, max: 255, minMessage: 'Le mot de passe doit contenir au moins {{ limit }} caractères.')]
        public string $newPassword = '',

        #[Assert\NotBlank(message: 'Confirmez le nouveau mot de passe.')]
        public string $newPasswordConfirm = '',
    ) {
    }

    #[Assert\Callback]
    public function validateMatch(ExecutionContextInterface $context): void
    {
        if ($this->newPassword !== $this->newPasswordConfirm) {
            $context->buildViolation('Les deux mots de passe ne correspondent pas.')
                ->atPath('newPasswordConfirm')
                ->addViolation();
        }

        if ('' !== $this->currentPassword && $this->currentPassword === $this->newPassword) {
            $context->buildViolation('Le nouveau mot de passe doit être différent de l\'actuel.')
                ->atPath('newPassword')
                ->addViolation();
        }
    }
}
