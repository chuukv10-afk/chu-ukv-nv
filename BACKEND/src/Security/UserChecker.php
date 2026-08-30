<?php

namespace App\Security;

use App\Entity\Personnel;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAccountStatusException;
use Symfony\Component\Security\Core\User\UserCheckerInterface;
use Symfony\Component\Security\Core\User\UserInterface;

class UserChecker implements UserCheckerInterface
{
    public function checkPreAuth(UserInterface $user): void
    {
        if (!$user instanceof Personnel) {
            return;
        }

        if (!$user->canAuthenticate()) {
            throw new CustomUserMessageAccountStatusException($user->getAuthenticationDeniedMessage());
        }
    }

    public function checkPostAuth(UserInterface $user): void
    {
    }
}
