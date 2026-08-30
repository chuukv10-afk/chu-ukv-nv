<?php

namespace App\Exception;

use Symfony\Component\HttpKernel\Exception\HttpException;

final class ConflictException extends HttpException
{
    public function __construct(string $message = 'Conflit avec une ressource existante.')
    {
        parent::__construct(409, $message);
    }
}
