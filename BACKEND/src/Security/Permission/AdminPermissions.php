<?php

namespace App\Security\Permission;

use App\Entity\Permission;

final class AdminPermissions
{
    public const ROLE_READ = 'admin.role.read';
    public const ROLE_CREATE = 'admin.role.create';
    public const ROLE_UPDATE = 'admin.role.update';
    public const ROLE_DELETE = 'admin.role.delete';
    public const ROLE_PERMISSION_READ = 'admin.role.permission.read';
    public const ROLE_PERMISSION_ASSIGN = 'admin.role.permission.assign';
    public const ROLE_PERMISSION_DELETE = 'admin.role.permission.delete';

    public const PERMISSION_READ = 'admin.permission.read';
    public const PERMISSION_CREATE = 'admin.permission.create';
    public const PERMISSION_UPDATE = 'admin.permission.update';
    public const PERMISSION_DELETE = 'admin.permission.delete';

    public const PERSONNEL_READ = 'admin.personnel.read';
    public const PERSONNEL_CREATE = 'admin.personnel.create';
    public const PERSONNEL_UPDATE = 'admin.personnel.update';
    public const PERSONNEL_DELETE = 'admin.personnel.delete';
    public const PERSONNEL_EXPORT = 'admin.personnel.export';

    /**
     * @return list<array{code: string, libelle: string, module: string}>
     */
    public static function allDefinitions(): array
    {
        return array_merge(
            self::crud('role', 'rôle', 'les rôles'),
            self::crud('permission', 'permission', 'les permissions'),
            self::crud('personnel', 'personnel', 'le personnel'),
            [
                [
                    'code' => self::PERSONNEL_EXPORT,
                    'libelle' => 'Exporter le personnel (PDF / Excel)',
                    'module' => Permission::MODULE_ADMIN,
                ],
            ],
            [
                [
                    'code' => self::ROLE_PERMISSION_READ,
                    'libelle' => 'Lire les affectations permissions/rôles',
                    'module' => Permission::MODULE_ADMIN,
                ],
                [
                    'code' => self::ROLE_PERMISSION_ASSIGN,
                    'libelle' => 'Affecter des permissions aux rôles',
                    'module' => Permission::MODULE_ADMIN,
                ],
                [
                    'code' => self::ROLE_PERMISSION_DELETE,
                    'libelle' => 'Supprimer des affectations permissions/rôles',
                    'module' => Permission::MODULE_ADMIN,
                ],
            ],
        );
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
                'libelle' => 'Créer une ' . $singular,
                'module' => Permission::MODULE_ADMIN,
            ],
            [
                'code' => $prefix . '.update',
                'libelle' => 'Modifier une ' . $singular,
                'module' => Permission::MODULE_ADMIN,
            ],
            [
                'code' => $prefix . '.delete',
                'libelle' => 'Supprimer une ' . $singular,
                'module' => Permission::MODULE_ADMIN,
            ],
        ];
    }
}
