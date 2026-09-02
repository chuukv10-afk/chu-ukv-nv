<?php

namespace App\Security\Permission;

use App\Entity\Permission;

/**
 * Permissions du module Organisation.
 */

final class OrganisationPermissions
{
    public const DEPARTEMENT_READ = 'organisation.departement.read';
    public const DEPARTEMENT_CREATE = 'organisation.departement.create';
    public const DEPARTEMENT_UPDATE = 'organisation.departement.update';
    public const DEPARTEMENT_DELETE = 'organisation.departement.delete';

    public const SERVICE_READ = 'organisation.service.read';
    public const SERVICE_CREATE = 'organisation.service.create';
    public const SERVICE_UPDATE = 'organisation.service.update';
    public const SERVICE_DELETE = 'organisation.service.delete';

    public const LIT_READ = 'organisation.lit.read';
    public const LIT_CREATE = 'organisation.lit.create';
    public const LIT_UPDATE = 'organisation.lit.update';
    public const LIT_DELETE = 'organisation.lit.delete';

    public const CHAMBRE_READ = 'organisation.chambre.read';
    public const CHAMBRE_CREATE = 'organisation.chambre.create';
    public const CHAMBRE_UPDATE = 'organisation.chambre.update';
    public const CHAMBRE_DELETE = 'organisation.chambre.delete';

    public const BLOC_READ = 'organisation.bloc.read';
    public const BLOC_CREATE = 'organisation.bloc.create';
    public const BLOC_UPDATE = 'organisation.bloc.update';
    public const BLOC_DELETE = 'organisation.bloc.delete';

    /**
     * @return list<array{code: string, libelle: string, module: string}>
     */
    public static function allDefinitions(): array
    {
        return array_merge(
            self::crud('departement', 'département', 'les départements'),
            self::crud('service', 'service', 'les services'),
            self::crud('lit', 'lit', 'les lits'),
            self::crud('chambre', 'chambre', 'les chambres'),
            self::crud('bloc', 'bloc', 'les blocs'),
        );
    }

    /**
     * @return list<array{code: string, libelle: string, module: string}>
     */
    public static function departementDefinitions(): array
    {
        return self::crud('departement', 'département', 'les départements');
    }

    /**
     * @return list<array{code: string, libelle: string, module: string}>
     */
    private static function crud(string $resource, string $singular, string $pluralLabel): array
    {
        $prefix = 'organisation.' . $resource;

        return [
            [
                'code' => $prefix . '.read',
                'libelle' => 'Lire ' . $pluralLabel,
                'module' => Permission::MODULE_ORGANISATION,
            ],
            [
                'code' => $prefix . '.create',
                'libelle' => 'Créer un ' . $singular,
                'module' => Permission::MODULE_ORGANISATION,
            ],
            [
                'code' => $prefix . '.update',
                'libelle' => 'Modifier un ' . $singular,
                'module' => Permission::MODULE_ORGANISATION,
            ],
            [
                'code' => $prefix . '.delete',
                'libelle' => 'Supprimer un ' . $singular,
                'module' => Permission::MODULE_ORGANISATION,
            ],
        ];
    }
}
