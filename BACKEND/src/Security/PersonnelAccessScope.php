<?php

namespace App\Security;

use App\Entity\Service;

/**
 * Périmètre effectif d'un personnel pour une permission donnée (listes et filtres).
 */
final readonly class PersonnelAccessScope
{
    /**
     * @param list<int> $serviceIds
     * @param list<int> $departementIds
     */
    public function __construct(
        public bool $unrestricted = false,
        public array $serviceIds = [],
        public array $departementIds = [],
    ) {
    }

    public function isRestricted(): bool
    {
        return !$this->unrestricted;
    }

    public function allowsNothing(): bool
    {
        return $this->isRestricted()
            && [] === $this->serviceIds
            && [] === $this->departementIds;
    }

    public function allowsService(?Service $service): bool
    {
        if (!$this->isRestricted()) {
            return true;
        }

        if (null === $service) {
            return false;
        }

        if (in_array($service->getId(), $this->serviceIds, true)) {
            return true;
        }

        $departementId = $service->getDepartement()?->getId();

        return null !== $departementId && in_array($departementId, $this->departementIds, true);
    }
}
