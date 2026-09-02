<?php

namespace App\Exception;

use Symfony\Component\HttpKernel\Exception\HttpException;

final class NotFoundException extends HttpException
{
    public function __construct(string $message = 'Ressource introuvable.')
    {
        parent::__construct(404, $message);
    }
}
