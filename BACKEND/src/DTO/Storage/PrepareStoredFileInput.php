<?php

namespace App\DTO\Storage;

use Symfony\Component\Validator\Constraints as Assert;

final class PrepareStoredFileInput
{
    public function __construct(
        #[Assert\NotBlank(message: 'Le type de fichier est obligatoire.')]
        public string $mimeType = '',

        #[Assert\Positive(message: 'La taille du fichier est invalide.')]
        public int $size = 0,
    ) {
    }
}
