<?php

namespace App\Service\Security;

use App\Security\Permission\AdminPermissions;
use App\Security\Permission\CliniquePermissions;
use App\Security\Permission\OrganisationPermissions;
use App\Security\Permission\ReferentielPermissions;

final class PermissionLabelResolver
{
    /** @var array<string, string> */
    private array $labels;

    public function __construct()
    {
        $this->labels = [];

        foreach ($this->allDefinitions() as $definition) {
            $this->labels[$definition['code']] = $definition['libelle'];
        }
    }

    public function resolve(string $code): string
    {
        $normalizedCode = strtoupper(trim($code));

        return match ($normalizedCode) {
            'ROLE_PERSONNEL' => 'Accès personnel à l\'application',
            'ROLE_ADMIN' => 'Administrateur système',
            default => $this->labels[$code] ?? $this->humanize($code),
        };
    }

    /**
     * @param list<string> $codes
     */
    public function buildMessage(array $codes): string
    {
        $codes = array_values(array_filter(array_map('strval', $codes)));

        if ([] === $codes) {
            return 'Accès refusé. Vous n\'avez pas la permission requise pour effectuer cette action.';
        }

        $labels = array_map([$this, 'resolve'], $codes);

        if (1 === count($labels)) {
            return sprintf('Accès refusé. Permission requise : « %s ».', $labels[0]);
        }

        $formatted = array_map(static fn (string $label): string => sprintf('« %s »', $label), $labels);

        return sprintf('Accès refusé. Permissions requises : %s.', implode(', ', $formatted));
    }

    /**
     * @return list<array{code: string, libelle: string, module: string}>
     */
    private function allDefinitions(): array
    {
        return array_merge(
            OrganisationPermissions::allDefinitions(),
            ReferentielPermissions::allDefinitions(),
            CliniquePermissions::allDefinitions(),
            AdminPermissions::allDefinitions(),
        );
    }

    private function humanize(string $code): string
    {
        if (str_starts_with(strtoupper($code), 'ROLE_')) {
            return ucfirst(strtolower(str_replace('_', ' ', substr($code, 5))));
        }

        $parts = explode('.', $code);
        $action = array_pop($parts) ?: 'accès';
        $resource = str_replace('_', ' ', (string) (array_pop($parts) ?: 'ressource'));

        $actionLabel = match ($action) {
            'read' => 'Lecture',
            'create' => 'Création',
            'update' => 'Modification',
            'delete' => 'Suppression',
            'assign' => 'Affectation',
            default => ucfirst($action),
        };

        return sprintf('%s — %s', ucfirst($resource), $actionLabel);
    }
}
