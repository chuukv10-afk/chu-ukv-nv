<?php

namespace App\Controller\Api\Trait;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

trait JsonResponseTrait
{
    protected function apiSuccess(
        mixed $data = null,
        string $message = '',
        int $status = Response::HTTP_OK,
    ): JsonResponse {
        $payload = [
            'success' => true,
            'message' => $message,
        ];

        if (null !== $data) {
            $payload['data'] = $data;
        }

        return $this->json($payload, $status);
    }
}
