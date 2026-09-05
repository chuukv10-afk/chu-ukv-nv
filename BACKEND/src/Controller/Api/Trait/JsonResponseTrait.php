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

    protected function apiPaginatedSuccess(
        array $items,
        int $page,
        int $limit,
        int $total,
        string $message = '',
        int $status = Response::HTTP_OK,
    ): JsonResponse {
        $totalPages = $total > 0 ? (int) ceil($total / $limit) : 0;

        return $this->json([
            'success' => true,
            'message' => $message,
            'data' => [
                'items' => $items,
                'pagination' => [
                    'page' => $page,
                    'limit' => $limit,
                    'total' => $total,
                    'totalPages' => $totalPages,
                ],
            ],
        ], $status);
    }
}
