<?php

namespace App\Security;

use App\Entity\Departement;
use App\Entity\Service;

/**
 * Représente le périmètre organisationnel d'un objet ou d'une action.
 */
final readonly class ScopeContext
{
    public function __construct(
        public ?Departement $departement = null,
        public ?Service $service = null,
    ) {
    }

    public function isEmpty(): bool
    {
        return null === $this->departement && null === $this->service;
    }
}
