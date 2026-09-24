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
    public const ACTE_CREATE = 'facturation.acte.create';
    public const ACTE_UPDATE = 'facturation.acte.update';
    public const ACTE_DELETE = 'facturation.acte.delete';
    public const ACTE_IMPORT = 'facturation.acte.import';
    public const ACTE_EXPORT = 'facturation.acte.export';

    public const FACTURE_READ = 'facturation.facture.read';
    public const FACTURE_CREATE = 'facturation.facture.create';
    public const FACTURE_UPDATE = 'facturation.facture.update';
    public const FACTURE_DELETE = 'facturation.facture.delete';
    public const FACTURE_VALIDER = 'facturation.facture.valider';
    public const FACTURE_EXPORT = 'facturation.facture.export';
    public const FACTURE_REMISE = 'facturation.facture.remise';

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
                'code' => self::ACTE_CREATE,
                'libelle' => 'Créer un acte tarifaire',
                'module' => Permission::MODULE_FACTURATION,
            ],
            [
                'code' => self::ACTE_UPDATE,
                'libelle' => 'Modifier un acte tarifaire',
                'module' => Permission::MODULE_FACTURATION,
            ],
            [
                'code' => self::ACTE_DELETE,
                'libelle' => 'Supprimer un acte tarifaire',
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
            [
                'code' => self::FACTURE_READ,
                'libelle' => 'Lire les factures',
                'module' => Permission::MODULE_FACTURATION,
            ],
            [
                'code' => self::FACTURE_CREATE,
                'libelle' => 'Créer une facture',
                'module' => Permission::MODULE_FACTURATION,
            ],
            [
                'code' => self::FACTURE_UPDATE,
                'libelle' => 'Modifier une facture',
                'module' => Permission::MODULE_FACTURATION,
            ],
            [
                'code' => self::FACTURE_DELETE,
                'libelle' => 'Supprimer une facture brouillon',
                'module' => Permission::MODULE_FACTURATION,
            ],
            [
                'code' => self::FACTURE_VALIDER,
                'libelle' => 'Valider une facture',
                'module' => Permission::MODULE_FACTURATION,
            ],
            [
                'code' => self::FACTURE_EXPORT,
                'libelle' => 'Exporter une facture',
                'module' => Permission::MODULE_FACTURATION,
            ],
            [
                'code' => self::FACTURE_REMISE,
                'libelle' => 'Appliquer une remise (globale ou par acte)',
                'module' => Permission::MODULE_FACTURATION,
            ],
        ];
    }
}
