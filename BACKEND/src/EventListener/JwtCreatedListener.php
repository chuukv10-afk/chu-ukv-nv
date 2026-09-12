<?php

namespace App\EventListener;

use Lexik\Bundle\JWTAuthenticationBundle\Event\JWTCreatedEvent;
use Lexik\Bundle\JWTAuthenticationBundle\Events;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

#[AsEventListener(event: Events::JWT_CREATED)]
final class JwtCreatedListener
{
    public function __invoke(JWTCreatedEvent $event): void
    {
        $data = $event->getData();
        $roles = $data['roles'] ?? [];
        if (!is_array($roles)) {
            return;
        }

        $data['roles'] = array_values(array_filter(
            $roles,
            static fn (mixed $role): bool => is_string($role) && str_starts_with($role, 'ROLE_'),
        ));
        $event->setData($data);
    }
}
