<?php

namespace App\Security\Permission;

use App\Entity\Permission;

/**
 * Permissions du module Référentiel.
 */
final class ReferentielPermissions
{
    public const GRADE_READ = 'referentiel.grade.read';
    public const GRADE_CREATE = 'referentiel.grade.create';
    public const GRADE_UPDATE = 'referentiel.grade.update';
    public const GRADE_DELETE = 'referentiel.grade.delete';

    public const FILIERE_READ = 'referentiel.filiere.read';
    public const FILIERE_CREATE = 'referentiel.filiere.create';
    public const FILIERE_UPDATE = 'referentiel.filiere.update';
    public const FILIERE_DELETE = 'referentiel.filiere.delete';

    public const SPECIALITE_READ = 'referentiel.specialite.read';
    public const SPECIALITE_CREATE = 'referentiel.specialite.create';
    public const SPECIALITE_UPDATE = 'referentiel.specialite.update';
    public const SPECIALITE_DELETE = 'referentiel.specialite.delete';
    public const SPECIALITE_EXPORT = 'referentiel.specialite.export';

    public const TYPE_EXAMEN_READ = 'referentiel.type_examen.read';
    public const TYPE_EXAMEN_CREATE = 'referentiel.type_examen.create';
    public const TYPE_EXAMEN_UPDATE = 'referentiel.type_examen.update';
    public const TYPE_EXAMEN_DELETE = 'referentiel.type_examen.delete';
    public const TYPE_EXAMEN_EXPORT = 'referentiel.type_examen.export';

    public const TYPE_ANTECEDENT_READ = 'referentiel.type_antecedent.read';
    public const TYPE_ANTECEDENT_CREATE = 'referentiel.type_antecedent.create';
    public const TYPE_ANTECEDENT_UPDATE = 'referentiel.type_antecedent.update';
    public const TYPE_ANTECEDENT_DELETE = 'referentiel.type_antecedent.delete';

    public const SIGNE_VITAL_READ = 'referentiel.signe_vital.read';
    public const SIGNE_VITAL_CREATE = 'referentiel.signe_vital.create';
    public const SIGNE_VITAL_UPDATE = 'referentiel.signe_vital.update';
    public const SIGNE_VITAL_DELETE = 'referentiel.signe_vital.delete';

    public const PLAINTE_READ = 'referentiel.plainte.read';
    public const PLAINTE_CREATE = 'referentiel.plainte.create';
    public const PLAINTE_UPDATE = 'referentiel.plainte.update';
    public const PLAINTE_DELETE = 'referentiel.plainte.delete';

    /**
     * @return list<array{code: string, libelle: string, module: string}>
     */
    public static function allDefinitions(): array
    {
        return array_merge(
            self::crud('grade', 'grade', 'les grades'),
            self::crud('filiere', 'filière UKV', 'les filières UKV'),
            self::crud('specialite', 'spécialité', 'les spécialités'),
            self::export(self::SPECIALITE_EXPORT, 'les spécialités'),
            self::crud('type_examen', 'type d\'examen', 'les types d\'examen'),
            self::export(self::TYPE_EXAMEN_EXPORT, 'les types d\'examen'),
            self::crud('type_antecedent', 'type d\'antécédent', 'les types d\'antécédent'),
            self::crud('signe_vital', 'signe vital', 'les signes vitaux'),
            self::crud('plainte', 'plainte', 'les plaintes'),
        );
    }

    /**
     * @return list<array{code: string, libelle: string, module: string}>
     */
    /**
     * @return list<array{code: string, libelle: string, module: string}>
     */
    private static function export(string $code, string $pluralLabel): array
    {
        return [
            [
                'code' => $code,
                'libelle' => 'Exporter ' . $pluralLabel . ' (PDF / Excel)',
                'module' => Permission::MODULE_REFERENTIEL,
            ],
        ];
    }

    /**
     * @return list<array{code: string, libelle: string, module: string}>
     */
    private static function crud(string $resource, string $singular, string $pluralLabel): array
    {
        $prefix = 'referentiel.' . $resource;

        return [
            [
                'code' => $prefix . '.read',
                'libelle' => 'Lire ' . $pluralLabel,
                'module' => Permission::MODULE_REFERENTIEL,
            ],
            [
                'code' => $prefix . '.create',
                'libelle' => 'Créer un ' . $singular,
                'module' => Permission::MODULE_REFERENTIEL,
            ],
            [
                'code' => $prefix . '.update',
                'libelle' => 'Modifier un ' . $singular,
                'module' => Permission::MODULE_REFERENTIEL,
            ],
            [
                'code' => $prefix . '.delete',
                'libelle' => 'Supprimer un ' . $singular,
                'module' => Permission::MODULE_REFERENTIEL,
            ],
        ];
    }
}
