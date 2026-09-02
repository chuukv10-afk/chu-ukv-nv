<?php

namespace App\Security\Permission;

use App\Entity\Permission;

/**
 * Permissions du module Clinique.
 */
final class CliniquePermissions
{
    public const EXAMEN_READ = 'clinique.examen.read';
    public const EXAMEN_CREATE = 'clinique.examen.create';
    public const EXAMEN_UPDATE = 'clinique.examen.update';
    public const EXAMEN_DELETE = 'clinique.examen.delete';

    /**
     * @return list<array{code: string, libelle: string, module: string}>
     */
    public static function allDefinitions(): array
    {
        return self::crud('examen', 'examen', 'les examens');
    }

    /**
     * @return list<array{code: string, libelle: string, module: string}>
     */
    private static function crud(string $resource, string $singular, string $pluralLabel): array
    {
        $prefix = 'clinique.' . $resource;

        return [
            [
                'code' => $prefix . '.read',
                'libelle' => 'Lire ' . $pluralLabel,
                'module' => Permission::MODULE_CLINIQUE,
            ],
            [
                'code' => $prefix . '.create',
                'libelle' => 'Créer un ' . $singular,
                'module' => Permission::MODULE_CLINIQUE,
            ],
            [
                'code' => $prefix . '.update',
                'libelle' => 'Modifier un ' . $singular,
                'module' => Permission::MODULE_CLINIQUE,
            ],
            [
                'code' => $prefix . '.delete',
                'libelle' => 'Supprimer un ' . $singular,
                'module' => Permission::MODULE_CLINIQUE,
            ],
        ];
    }
}
