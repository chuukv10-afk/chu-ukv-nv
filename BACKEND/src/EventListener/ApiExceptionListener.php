<?php

namespace App\EventListener;

use App\Service\Security\PermissionLabelResolver;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Validator\Exception\ValidationFailedException;

#[AsEventListener(event: KernelEvents::EXCEPTION, priority: 0)]
final class ApiExceptionListener
{
    public function __construct(
        private readonly PermissionLabelResolver $permissionLabelResolver,
    ) {
    }

    public function onKernelException(ExceptionEvent $event): void
    {
        if (!str_starts_with($event->getRequest()->getPathInfo(), '/api')) {
            return;
        }

        $throwable = $event->getThrowable();
        $validationException = $this->resolveValidationFailedException($throwable);

        if (null !== $validationException) {
            $event->setResponse($this->errorResponse(
                'Données invalides.',
                JsonResponse::HTTP_UNPROCESSABLE_ENTITY,
                $this->formatViolations($validationException),
            ));

            return;
        }

        $accessDeniedException = $this->resolveAccessDeniedException($throwable);
        if (null !== $accessDeniedException) {
            $requiredPermissions = $accessDeniedException->getAttributes();
            $event->setResponse($this->accessDeniedResponse($requiredPermissions));

            return;
        }

        if ($throwable instanceof AuthenticationException) {
            $event->setResponse($this->errorResponse(
                'Authentification requise. Veuillez vous connecter.',
                JsonResponse::HTTP_UNAUTHORIZED,
            ));

            return;
        }

        if ($throwable instanceof HttpExceptionInterface) {
            $message = $throwable->getMessage();
            if ('' === trim($message) || $this->isGenericAccessDeniedMessage($message)) {
                $message = match ($throwable->getStatusCode()) {
                    JsonResponse::HTTP_NOT_FOUND => 'Ressource introuvable.',
                    JsonResponse::HTTP_FORBIDDEN => $this->permissionLabelResolver->buildMessage([]),
                    JsonResponse::HTTP_UNAUTHORIZED => 'Authentification requise. Veuillez vous connecter.',
                    JsonResponse::HTTP_CONFLICT => 'Conflit avec une ressource existante.',
                    default => 'Une erreur est survenue.',
                };
            }

            $event->setResponse($this->errorResponse(
                $message,
                $throwable->getStatusCode(),
                headers: $throwable->getHeaders(),
            ));
        }
    }

    /**
     * @param list<string> $requiredPermissions
     */
    private function accessDeniedResponse(array $requiredPermissions): JsonResponse
    {
        return new JsonResponse([
            'success' => false,
            'message' => $this->permissionLabelResolver->buildMessage($requiredPermissions),
            'requiredPermissions' => array_values($requiredPermissions),
        ], JsonResponse::HTTP_FORBIDDEN);
    }

    private function resolveAccessDeniedException(\Throwable $throwable): ?AccessDeniedException
    {
        if ($throwable instanceof AccessDeniedException) {
            return $throwable;
        }

        $current = $throwable;
        while (null !== $current) {
            if ($current instanceof AccessDeniedException) {
                return $current;
            }

            $current = $current->getPrevious();
        }

        if ($throwable instanceof AccessDeniedHttpException) {
            return $this->resolveAccessDeniedException($throwable->getPrevious() ?? $throwable);
        }

        return null;
    }

    private function isGenericAccessDeniedMessage(string $message): bool
    {
        return in_array(trim($message), [
            'Access Denied.',
            'Access Denied',
            'Forbidden',
            'Accès refusé.',
            'Accès refusé',
        ], true);
    }

    /**
     * @param list<array{field: string, message: string}> $errors
     * @param array<string, list<string>> $headers
     */
    private function errorResponse(string $message, int $status, array $errors = [], array $headers = []): JsonResponse
    {
        $payload = [
            'success' => false,
            'message' => $message,
        ];

        if ([] !== $errors) {
            $payload['errors'] = $errors;
        }

        return new JsonResponse($payload, $status, $headers);
    }

    private function resolveValidationFailedException(\Throwable $throwable): ?ValidationFailedException
    {
        if ($throwable instanceof ValidationFailedException) {
            return $throwable;
        }

        $previous = $throwable->getPrevious();
        if ($previous instanceof ValidationFailedException) {
            return $previous;
        }

        return null;
    }

    /**
     * @return list<array{field: string, message: string}>
     */
    private function formatViolations(ValidationFailedException $exception): array
    {
        $errors = [];

        foreach ($exception->getViolations() as $violation) {
            $errors[] = [
                'field' => $violation->getPropertyPath(),
                'message' => (string) $violation->getMessage(),
            ];
        }

        return $errors;
    }
}
