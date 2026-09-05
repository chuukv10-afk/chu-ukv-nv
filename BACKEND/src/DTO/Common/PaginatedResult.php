<?php

namespace App\DTO\Common;

/**
 * @template T
 */
final class PaginatedResult
{
    /**
     * @param list<T> $items
     */
    public function __construct(
        public readonly array $items,
        public readonly int $page,
        public readonly int $limit,
        public readonly int $total,
    ) {
    }

    public function totalPages(): int
    {
        if ($this->total <= 0) {
            return 0;
        }

        return (int) ceil($this->total / $this->limit);
    }

    /**
     * @return array{
     *     items: list<T>,
     *     pagination: array{
     *         page: int,
     *         limit: int,
     *         total: int,
     *         totalPages: int
     *     }
     * }
     */
    public function toArray(): array
    {
        return [
            'items' => $this->items,
            'pagination' => [
                'page' => $this->page,
                'limit' => $this->limit,
                'total' => $this->total,
                'totalPages' => $this->totalPages(),
            ],
        ];
    }
}
