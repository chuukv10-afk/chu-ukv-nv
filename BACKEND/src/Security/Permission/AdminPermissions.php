<?php

namespace App\Security\Permission;

use App\Entity\Permission;

final class AdminPermissions
{
    public const ROLE_READ = 'admin.role.read';
    public const ROLE_CREATE = 'admin.role.create';
    public const ROLE_UPDATE = 'admin.role.update';
    public const ROLE_DELETE = 'admin.role.delete';

    /**
     * @return list<array{code: string, libelle: string, module: string}>
     */
    public static function allDefinitions(): array
    {
        return self::crud('role', 'rôle', 'les rôles');
    }

    /**
     * @return list<array{code: string, libelle: string, module: string}>
     */
    private static function crud(string $resource, string $singular, string $pluralLabel): array
    {
        $prefix = 'admin.' . $resource;

        return [
            [
                'code' => $prefix . '.read',
                'libelle' => 'Lire ' . $pluralLabel,
                'module' => Permission::MODULE_ADMIN,
            ],
            [
                'code' => $prefix . '.create',
                'libelle' => 'Créer un ' . $singular,
                'module' => Permission::MODULE_ADMIN,
            ],
            [
                'code' => $prefix . '.update',
                'libelle' => 'Modifier un ' . $singular,
                'module' => Permission::MODULE_ADMIN,
            ],
            [
                'code' => $prefix . '.delete',
                'libelle' => 'Supprimer un ' . $singular,
                'module' => Permission::MODULE_ADMIN,
            ],
        ];
    }
}
