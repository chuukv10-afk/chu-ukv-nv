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

    public const SPECIALITE_READ = 'referentiel.specialite.read';
    public const SPECIALITE_CREATE = 'referentiel.specialite.create';
    public const SPECIALITE_UPDATE = 'referentiel.specialite.update';
    public const SPECIALITE_DELETE = 'referentiel.specialite.delete';

    public const TYPE_EXAMEN_READ = 'referentiel.type_examen.read';
    public const TYPE_EXAMEN_CREATE = 'referentiel.type_examen.create';
    public const TYPE_EXAMEN_UPDATE = 'referentiel.type_examen.update';
    public const TYPE_EXAMEN_DELETE = 'referentiel.type_examen.delete';

    public const TYPE_ANTECEDENT_READ = 'referentiel.type_antecedent.read';
    public const TYPE_ANTECEDENT_CREATE = 'referentiel.type_antecedent.create';
    public const TYPE_ANTECEDENT_UPDATE = 'referentiel.type_antecedent.update';
    public const TYPE_ANTECEDENT_DELETE = 'referentiel.type_antecedent.delete';

    /**
     * @return list<array{code: string, libelle: string, module: string}>
     */
    public static function allDefinitions(): array
    {
        return array_merge(
            self::crud('grade', 'grade', 'les grades'),
            self::crud('specialite', 'spécialité', 'les spécialités'),
            self::crud('type_examen', 'type d\'examen', 'les types d\'examen'),
            self::crud('type_antecedent', 'type d\'antécédent', 'les types d\'antécédent'),
        );
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
