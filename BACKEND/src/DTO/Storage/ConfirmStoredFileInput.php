<?php

namespace App\DTO\Storage;

use Symfony\Component\Validator\Constraints as Assert;

final class ConfirmStoredFileInput
{
    public function __construct(
        #[Assert\NotBlank(message: 'Le nom du fichier est obligatoire.')]
        public string $filename = '',
    ) {
    }
}
