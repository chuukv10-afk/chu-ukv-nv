<?php

namespace App\Security\Voter;

use App\Entity\Personnel;
use App\Security\PermissionChecker;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * Voter générique : intercepte les attributs de type "module.ressource.action".
 *
 * @extends Voter<string, mixed>
 */
final class PermissionVoter extends Voter
{
    public function __construct(
        private readonly PermissionChecker $permissionChecker,
    ) {
    }

    protected function supports(string $attribute, mixed $subject): bool
    {
        return str_contains($attribute, '.');
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool
    {
        $user = $token->getUser();
        if (!$user instanceof Personnel) {
            return false;
        }

        return $this->permissionChecker->isGranted($user, $attribute, $subject);
    }
}
