<?php

namespace App\EventListener;

use App\Entity\Personnel;
use App\Service\Auth\RefreshTokenService;
use Lexik\Bundle\JWTAuthenticationBundle\Event\AuthenticationSuccessEvent;
use Lexik\Bundle\JWTAuthenticationBundle\Events;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

#[AsEventListener(event: Events::AUTHENTICATION_SUCCESS)]
final class JwtAuthenticationSuccessListener
{
    public function __construct(
        private readonly RefreshTokenService $refreshTokenService,
    ) {
    }

    public function __invoke(AuthenticationSuccessEvent $event): void
    {
        $user = $event->getUser();
        if (!$user instanceof Personnel) {
            return;
        }

        $data = $event->getData();
        $data['refreshToken'] = $this->refreshTokenService->issue($user);
        $event->setData($data);
    }
}
