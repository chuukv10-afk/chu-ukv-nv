<?php

namespace App\DTO\Imagerie;

use Symfony\Component\Validator\Constraints as Assert;

final class ConfirmImagerieImageInput
{
    public function __construct(
        #[Assert\NotBlank]
        public string $filename = '',

        #[Assert\NotBlank]
        public string $originalName = '',

        #[Assert\NotBlank]
        public string $mimeType = '',

        #[Assert\Positive]
        public int $size = 0,
    ) {
    }
}
