<?php

namespace App\Service\Auth;

use App\Entity\Personnel;
use App\Entity\RefreshToken;
use App\Repository\RefreshTokenRepository;
use Doctrine\ORM\EntityManagerInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;

final class RefreshTokenService
{
    private const TTL_DAYS = 7;

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly RefreshTokenRepository $refreshTokenRepository,
        private readonly JWTTokenManagerInterface $jwtTokenManager,
    ) {
    }

    public function issue(Personnel $personnel): string
    {
        $plain = bin2hex(random_bytes(32));
        $token = (new RefreshToken())
            ->setTokenHash($this->hash($plain))
            ->setPersonnel($personnel)
            ->setCreatedAt(new \DateTimeImmutable())
            ->setExpiresAt(new \DateTimeImmutable('+' . self::TTL_DAYS . ' days'));

        $this->entityManager->persist($token);
        $this->entityManager->flush();

        return $plain;
    }

    /** @return array{token: string, refreshToken: string} */
    public function rotate(string $plainRefreshToken): array
    {
        $current = $this->refreshTokenRepository->findValidByHash($this->hash($plainRefreshToken));
        if (null === $current) {
            throw new UnauthorizedHttpException('Bearer', 'Refresh token invalide ou expiré.');
        }

        $personnel = $current->getPersonnel();
        if (!$personnel instanceof Personnel) {
            throw new UnauthorizedHttpException('Bearer', 'Refresh token invalide.');
        }

        $this->entityManager->remove($current);
        $this->entityManager->flush();

        return [
            'token' => $this->jwtTokenManager->create($personnel),
            'refreshToken' => $this->issue($personnel),
        ];
    }

    private function hash(string $plain): string
    {
        return hash('sha256', $plain);
    }
}
