<?php

namespace App\DTO\Intendance;

use Symfony\Component\Validator\Constraints as Assert;

final class EtiquettesQuery
{
    public function __construct(
        #[Assert\NotBlank(message: 'Sélectionnez au moins un bien.')]
        public string $ids = '',
    ) {
    }

    /**
     * @return list<int>
     */
    public function parsedIds(): array
    {
        $ids = [];
        foreach (preg_split('/[,\s]+/', $this->ids) ?: [] as $raw) {
            if ('' === $raw || !ctype_digit($raw)) {
                continue;
            }
            $id = (int) $raw;
            if ($id > 0) {
                $ids[] = $id;
            }
        }

        return array_values(array_unique($ids));
    }
}
