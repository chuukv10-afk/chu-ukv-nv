<?php

namespace App\Security\Permission;

use App\Entity\Permission;

/**
 * Permissions du module Facturation.
 */
final class FacturationPermissions
{
    public const STRUCTURE_READ = 'facturation.structure.read';
    public const STRUCTURE_CREATE = 'facturation.structure.create';
    public const STRUCTURE_UPDATE = 'facturation.structure.update';
    public const STRUCTURE_DELETE = 'facturation.structure.delete';

    public const ACTE_READ = 'facturation.acte.read';
    public const ACTE_IMPORT = 'facturation.acte.import';
    public const ACTE_EXPORT = 'facturation.acte.export';

    /**
     * @return list<array{code: string, libelle: string, module: string}>
     */
    public static function allDefinitions(): array
    {
        return [
            [
                'code' => self::STRUCTURE_READ,
                'libelle' => 'Lire les structures (mutuelles, ONG, assurances…)',
                'module' => Permission::MODULE_FACTURATION,
            ],
            [
                'code' => self::STRUCTURE_CREATE,
                'libelle' => 'Créer une structure',
                'module' => Permission::MODULE_FACTURATION,
            ],
            [
                'code' => self::STRUCTURE_UPDATE,
                'libelle' => 'Modifier une structure',
                'module' => Permission::MODULE_FACTURATION,
            ],
            [
                'code' => self::STRUCTURE_DELETE,
                'libelle' => 'Supprimer une structure',
                'module' => Permission::MODULE_FACTURATION,
            ],
            [
                'code' => self::ACTE_READ,
                'libelle' => 'Lire la grille tarifaire',
                'module' => Permission::MODULE_FACTURATION,
            ],
            [
                'code' => self::ACTE_IMPORT,
                'libelle' => 'Importer la grille tarifaire (Excel)',
                'module' => Permission::MODULE_FACTURATION,
            ],
            [
                'code' => self::ACTE_EXPORT,
                'libelle' => 'Exporter la grille tarifaire (PDF / Excel)',
                'module' => Permission::MODULE_FACTURATION,
            ],
        ];
    }
}
