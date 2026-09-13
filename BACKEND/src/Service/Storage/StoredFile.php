<?php

namespace App\Service\Storage;

final class StoredFile
{
    public function __construct(
        public readonly string $contents,
        public readonly string $mimeType,
        public readonly ?int $lastModified = null,
    ) {
    }
}
