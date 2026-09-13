<?php

namespace App\Service\Storage;

use Symfony\Component\HttpFoundation\Response;

final class StoredFileResponse
{
    public static function create(StoredFile $file): Response
    {
        $response = new Response($file->contents);
        $response->headers->set('Content-Type', $file->mimeType);
        $response->setPrivate();
        $response->headers->addCacheControlDirective('no-store');

        return $response;
    }
}
