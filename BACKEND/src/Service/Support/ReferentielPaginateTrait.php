<?php

namespace App\Service\Support;

use App\DTO\Common\PaginatedResult;
use App\DTO\Referentiel\ReferentielListQuery;
use Symfony\Component\Validator\Exception\ValidationFailedException;

trait ReferentielPaginateTrait
{
    /**
     * @return array{items: list<object>, total: int}
     */
    abstract protected function paginateEntities(int $page, int $limit, ?string $search): array;

    public function paginate(ReferentielListQuery $query): PaginatedResult
    {
        $errors = $this->validator->validate($query);
        if (count($errors) > 0) {
            throw new ValidationFailedException($query, $errors);
        }

        $result = $this->paginateEntities($query->page, $query->limit, $query->search);

        return new PaginatedResult(
            array_map([$this, 'serializeSummary'], $result['items']),
            $query->page,
            $query->limit,
            $result['total'],
        );
    }
}
