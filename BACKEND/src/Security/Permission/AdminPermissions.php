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

    public const SIGNATURE_READ = 'admin.signature.read';
    public const SIGNATURE_UPDATE = 'admin.signature.update';

    public const PROFIL_IDENTITE_READ = 'admin.profil.identite.read';
    public const PROFIL_IDENTITE_UPDATE = 'admin.profil.identite.update';
    public const PROFIL_AUTH_READ = 'admin.profil.auth.read';
    public const PROFIL_AUTH_UPDATE = 'admin.profil.auth.update';
    public const PROFIL_AFFECTATION_READ = 'admin.profil.affectation.read';
    public const PROFIL_PHOTO_READ = 'admin.profil.photo.read';
    public const PROFIL_PHOTO_UPDATE = 'admin.profil.photo.update';

    public const DATABASE_MANAGE = 'admin.database.manage';
    public const DATABASE_EXPORT = 'admin.database.export';
    public const DATABASE_TRUNCATE = 'admin.database.truncate';
    public const DATABASE_IMPORT = 'admin.database.import';

    public const DASHBOARD_VIEW = 'admin.dashboard.view';

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
                [
                    'code' => self::DATABASE_MANAGE,
                    'libelle' => 'Consulter l\'administration de la base de données',
                    'module' => Permission::MODULE_ADMIN,
                ],
                [
                    'code' => self::DATABASE_EXPORT,
                    'libelle' => 'Exporter des tables (SQL / Excel)',
                    'module' => Permission::MODULE_ADMIN,
                ],
                [
                    'code' => self::DATABASE_TRUNCATE,
                    'libelle' => 'Vider des tables de la base',
                    'module' => Permission::MODULE_ADMIN,
                ],
                [
                    'code' => self::DATABASE_IMPORT,
                    'libelle' => 'Importer un dump SQL',
                    'module' => Permission::MODULE_ADMIN,
                ],
                [
                    'code' => self::DASHBOARD_VIEW,
                    'libelle' => 'Consulter le tableau de bord institutionnel',
                    'module' => Permission::MODULE_ADMIN,
                ],
                [
                    'code' => self::SIGNATURE_READ,
                    'libelle' => 'Consulter une signature manuscrite',
                    'module' => Permission::MODULE_ADMIN,
                ],
                [
                    'code' => self::SIGNATURE_UPDATE,
                    'libelle' => 'Lier ou remplacer sa signature manuscrite',
                    'module' => Permission::MODULE_ADMIN,
                ],
                [
                    'code' => self::PROFIL_IDENTITE_READ,
                    'libelle' => 'Profil — voir les informations personnelles',
                    'module' => Permission::MODULE_ADMIN,
                ],
                [
                    'code' => self::PROFIL_IDENTITE_UPDATE,
                    'libelle' => 'Profil — modifier les informations personnelles',
                    'module' => Permission::MODULE_ADMIN,
                ],
                [
                    'code' => self::PROFIL_AUTH_READ,
                    'libelle' => 'Profil — voir les informations d\'authentification',
                    'module' => Permission::MODULE_ADMIN,
                ],
                [
                    'code' => self::PROFIL_AUTH_UPDATE,
                    'libelle' => 'Profil — modifier son mot de passe',
                    'module' => Permission::MODULE_ADMIN,
                ],
                [
                    'code' => self::PROFIL_AFFECTATION_READ,
                    'libelle' => 'Profil — voir son affectation',
                    'module' => Permission::MODULE_ADMIN,
                ],
                [
                    'code' => self::PROFIL_PHOTO_READ,
                    'libelle' => 'Profil — voir la photo de profil',
                    'module' => Permission::MODULE_ADMIN,
                ],
                [
                    'code' => self::PROFIL_PHOTO_UPDATE,
                    'libelle' => 'Profil — modifier la photo de profil',
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
