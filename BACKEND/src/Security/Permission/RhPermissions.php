<?php

namespace App\Security\Permission;

use App\Entity\Permission;

/**
 * Permissions du module Ressources humaines (chef du personnel).
 */
final class RhPermissions
{
    public const PERSONNEL_READ = 'rh.personnel.read';
    public const PERSONNEL_CREATE = 'rh.personnel.create';
    public const PERSONNEL_UPDATE = 'rh.personnel.update';
    public const PERSONNEL_DELETE = 'rh.personnel.delete';
    public const PERSONNEL_EXPORT = 'rh.personnel.export';

    public const PAIE_READ = 'rh.paie.read';
    public const PAIE_CREATE = 'rh.paie.create';
    public const PAIE_UPDATE = 'rh.paie.update';
    public const PAIE_VALIDATE = 'rh.paie.validate';

    /**
     * @return list<array{code: string, libelle: string, module: string}>
     */
    public static function allDefinitions(): array
    {
        return [
            [
                'code' => self::PERSONNEL_READ,
                'libelle' => 'RH — consulter les fiches du personnel',
                'module' => Permission::MODULE_RH,
            ],
            [
                'code' => self::PERSONNEL_CREATE,
                'libelle' => 'RH — créer une fiche personnel',
                'module' => Permission::MODULE_RH,
            ],
            [
                'code' => self::PERSONNEL_UPDATE,
                'libelle' => 'RH — modifier une fiche personnel',
                'module' => Permission::MODULE_RH,
            ],
            [
                'code' => self::PERSONNEL_DELETE,
                'libelle' => 'RH — supprimer une fiche personnel',
                'module' => Permission::MODULE_RH,
            ],
            [
                'code' => self::PERSONNEL_EXPORT,
                'libelle' => 'RH — exporter le personnel (PDF / Excel)',
                'module' => Permission::MODULE_RH,
            ],
            [
                'code' => self::PAIE_READ,
                'libelle' => 'RH — consulter la paie du personnel',
                'module' => Permission::MODULE_RH,
            ],
            [
                'code' => self::PAIE_CREATE,
                'libelle' => 'RH — ouvrir une période de paie',
                'module' => Permission::MODULE_RH,
            ],
            [
                'code' => self::PAIE_UPDATE,
                'libelle' => 'RH — générer et ajuster un état de paie',
                'module' => Permission::MODULE_RH,
            ],
            [
                'code' => self::PAIE_VALIDATE,
                'libelle' => 'RH — valider et geler un état de paie',
                'module' => Permission::MODULE_RH,
            ],
        ];
    }
}
