<?php

namespace App\Security\Permission;

use App\Entity\Permission;

/**
 * Permissions du module Patient / DPI.
 */
final class PatientPermissions
{
    public const PATIENT_READ = 'patient.read';
    public const PATIENT_CREATE = 'patient.create';
    public const PATIENT_UPDATE = 'patient.update';
    public const PATIENT_DELETE = 'patient.delete';
    public const PATIENT_EXPORT = 'patient.export';

    public const DPI_READ = 'patient.dpi.read';
    public const DPI_UPDATE = 'patient.dpi.update';

    public const DPI_ANTECEDENT_READ = 'patient.dpi.antecedent.read';
    public const DPI_ANTECEDENT_CREATE = 'patient.dpi.antecedent.create';
    public const DPI_ANTECEDENT_DELETE = 'patient.dpi.antecedent.delete';

    /**
     * @return list<array{code: string, libelle: string, module: string}>
     */
    public static function allDefinitions(): array
    {
        return array_merge(
            self::crud('patient', 'patient', 'les patients'),
            [
                [
                    'code' => self::PATIENT_EXPORT,
                    'libelle' => 'Exporter les patients (PDF / Excel)',
                    'module' => Permission::MODULE_PATIENT,
                ],
                [
                    'code' => self::DPI_READ,
                    'libelle' => 'Consulter un dossier patient (DPI)',
                    'module' => Permission::MODULE_PATIENT,
                ],
                [
                    'code' => self::DPI_UPDATE,
                    'libelle' => 'Modifier le statut d\'un dossier patient (DPI)',
                    'module' => Permission::MODULE_PATIENT,
                ],
                [
                    'code' => self::DPI_ANTECEDENT_READ,
                    'libelle' => 'Consulter les antécédents d\'un dossier patient',
                    'module' => Permission::MODULE_PATIENT,
                ],
                [
                    'code' => self::DPI_ANTECEDENT_CREATE,
                    'libelle' => 'Ajouter un antécédent au dossier patient',
                    'module' => Permission::MODULE_PATIENT,
                ],
                [
                    'code' => self::DPI_ANTECEDENT_DELETE,
                    'libelle' => 'Supprimer un antécédent du dossier patient',
                    'module' => Permission::MODULE_PATIENT,
                ],
            ],
        );
    }

    /**
     * @return list<array{code: string, libelle: string, module: string}>
     */
    private static function crud(string $resource, string $singular, string $pluralLabel): array
    {
        $prefix = $resource;

        return [
            [
                'code' => $prefix . '.read',
                'libelle' => 'Lire ' . $pluralLabel,
                'module' => Permission::MODULE_PATIENT,
            ],
            [
                'code' => $prefix . '.create',
                'libelle' => 'Créer un ' . $singular,
                'module' => Permission::MODULE_PATIENT,
            ],
            [
                'code' => $prefix . '.update',
                'libelle' => 'Modifier un ' . $singular,
                'module' => Permission::MODULE_PATIENT,
            ],
            [
                'code' => $prefix . '.delete',
                'libelle' => 'Supprimer un ' . $singular,
                'module' => Permission::MODULE_PATIENT,
            ],
        ];
    }
}
