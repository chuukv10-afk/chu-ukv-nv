<?php

namespace App\Security;

use App\Entity\Departement;
use App\Entity\Personnel;
use App\Entity\Service;

/**
 * Extrait le département / service d'une entité pour comparer avec le périmètre du personnel.
 */
final class ScopeResolver
{
    public function resolve(?object $subject): ScopeContext
    {
        if (null === $subject) {
            return new ScopeContext();
        }

        if ($subject instanceof Departement) {
            return new ScopeContext(departement: $subject);
        }

        if ($subject instanceof Service) {
            return new ScopeContext(
                departement: $subject->getDepartement(),
                service: $subject,
            );
        }

        if ($subject instanceof Personnel) {
            $service = $subject->getService();

            return new ScopeContext(
                departement: $service?->getDepartement(),
                service: $service,
            );
        }

        return new ScopeContext();
    }
}
