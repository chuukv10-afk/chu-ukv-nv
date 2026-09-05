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
    public const EXAMEN_EXPORT = 'clinique.examen.export';

    public const MALADIE_READ = 'clinique.maladie.read';
    public const MALADIE_CREATE = 'clinique.maladie.create';
    public const MALADIE_UPDATE = 'clinique.maladie.update';
    public const MALADIE_DELETE = 'clinique.maladie.delete';
    public const MALADIE_EXPORT = 'clinique.maladie.export';

    public const VISITE_READ = 'clinique.visite.read';
    public const VISITE_CREATE = 'clinique.visite.create';
    public const VISITE_UPDATE = 'clinique.visite.update';
    public const VISITE_DELETE = 'clinique.visite.delete';
    public const VISITE_EXPORT = 'clinique.visite.export';

    public const CONSULTATION_READ = 'clinique.consultation.read';
    public const CONSULTATION_CREATE = 'clinique.consultation.create';
    public const CONSULTATION_UPDATE = 'clinique.consultation.update';
    public const CONSULTATION_DELETE = 'clinique.consultation.delete';
    public const CONSULTATION_CLOSE = 'clinique.consultation.close';
    public const CONSULTATION_EXPORT = 'clinique.consultation.export';

    public const DIAGNOSTIC_READ = 'clinique.diagnostic.read';
    public const DIAGNOSTIC_CREATE = 'clinique.diagnostic.create';
    public const DIAGNOSTIC_DELETE = 'clinique.diagnostic.delete';

    public const DEMANDE_EXAMEN_READ = 'clinique.demande_examen.read';
    public const DEMANDE_EXAMEN_CREATE = 'clinique.demande_examen.create';
    public const DEMANDE_EXAMEN_CANCEL = 'clinique.demande_examen.cancel';
    public const DEMANDE_EXAMEN_SAISIE_RESULTAT = 'clinique.demande_examen.saisie_resultat';
    public const DEMANDE_EXAMEN_VALIDATE = 'clinique.demande_examen.validate';

    /**
     * @return list<array{code: string, libelle: string, module: string}>
     */
    public static function allDefinitions(): array
    {
        return array_merge(
            self::crud('examen', 'examen', 'les examens'),
            [
                [
                    'code' => self::EXAMEN_EXPORT,
                    'libelle' => 'Exporter les examens (PDF / Excel)',
                    'module' => Permission::MODULE_CLINIQUE,
                ],
            ],
            self::crud('maladie', 'maladie CIM-10', 'les maladies CIM-10'),
            [
                [
                    'code' => self::MALADIE_EXPORT,
                    'libelle' => 'Exporter les maladies CIM-10 (PDF / Excel)',
                    'module' => Permission::MODULE_CLINIQUE,
                ],
            ],
            self::crud('visite', 'visite', 'les visites'),
            [
                [
                    'code' => self::VISITE_EXPORT,
                    'libelle' => 'Exporter les visites (PDF / Excel)',
                    'module' => Permission::MODULE_CLINIQUE,
                ],
            ],
            self::crud('consultation', 'consultation', 'les consultations'),
            [
                [
                    'code' => self::CONSULTATION_CLOSE,
                    'libelle' => 'Clôturer une consultation',
                    'module' => Permission::MODULE_CLINIQUE,
                ],
                [
                    'code' => self::CONSULTATION_EXPORT,
                    'libelle' => 'Imprimer / exporter une consultation',
                    'module' => Permission::MODULE_CLINIQUE,
                ],
            ],
            [
                [
                    'code' => self::DIAGNOSTIC_READ,
                    'libelle' => 'Consulter les diagnostics',
                    'module' => Permission::MODULE_CLINIQUE,
                ],
                [
                    'code' => self::DIAGNOSTIC_CREATE,
                    'libelle' => 'Ajouter un diagnostic',
                    'module' => Permission::MODULE_CLINIQUE,
                ],
                [
                    'code' => self::DIAGNOSTIC_DELETE,
                    'libelle' => 'Supprimer un diagnostic',
                    'module' => Permission::MODULE_CLINIQUE,
                ],
                [
                    'code' => self::DEMANDE_EXAMEN_READ,
                    'libelle' => 'Consulter les demandes d\'examen',
                    'module' => Permission::MODULE_CLINIQUE,
                ],
                [
                    'code' => self::DEMANDE_EXAMEN_CREATE,
                    'libelle' => 'Prescrire une demande d\'examen',
                    'module' => Permission::MODULE_CLINIQUE,
                ],
                [
                    'code' => self::DEMANDE_EXAMEN_CANCEL,
                    'libelle' => 'Annuler une demande d\'examen',
                    'module' => Permission::MODULE_CLINIQUE,
                ],
                [
                    'code' => self::DEMANDE_EXAMEN_SAISIE_RESULTAT,
                    'libelle' => 'Saisir le résultat d\'une demande d\'examen',
                    'module' => Permission::MODULE_CLINIQUE,
                ],
                [
                    'code' => self::DEMANDE_EXAMEN_VALIDATE,
                    'libelle' => 'Valider le résultat d\'une demande d\'examen',
                    'module' => Permission::MODULE_CLINIQUE,
                ],
            ],
        );
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
