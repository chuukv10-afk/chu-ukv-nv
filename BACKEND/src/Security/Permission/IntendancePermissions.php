<?php

namespace App\Security\Permission;

use App\Entity\Permission;

/**
 * Permissions du module Intendance.
 */
final class IntendancePermissions
{
    public const FAMILLE_READ = 'intendance.famille.read';
    public const FAMILLE_CREATE = 'intendance.famille.create';
    public const FAMILLE_UPDATE = 'intendance.famille.update';
    public const FAMILLE_DELETE = 'intendance.famille.delete';

    public const TYPE_READ = 'intendance.type.read';
    public const TYPE_CREATE = 'intendance.type.create';
    public const TYPE_UPDATE = 'intendance.type.update';
    public const TYPE_DELETE = 'intendance.type.delete';

    public const LOCAL_READ = 'intendance.local.read';
    public const LOCAL_CREATE = 'intendance.local.create';
    public const LOCAL_UPDATE = 'intendance.local.update';
    public const LOCAL_DELETE = 'intendance.local.delete';

    public const BIEN_READ = 'intendance.bien.read';
    public const BIEN_CREATE = 'intendance.bien.create';
    public const BIEN_UPDATE = 'intendance.bien.update';
    public const BIEN_DELETE = 'intendance.bien.delete';
    public const BIEN_EXPORT = 'intendance.bien.export';
    public const ETIQUETTE_GENERATE = 'intendance.etiquette.generate';

    public const SYNTHESE_READ = 'intendance.synthese.read';

    public const CAMPAGNE_READ = 'intendance.campagne.read';
    public const CAMPAGNE_CREATE = 'intendance.campagne.create';
    public const CAMPAGNE_UPDATE = 'intendance.campagne.update';
    public const CAMPAGNE_VISER = 'intendance.campagne.viser';
    public const CAMPAGNE_VISER_TOUS = 'intendance.campagne.viser_tous';
    public const CAMPAGNE_CLOTURER = 'intendance.campagne.cloturer';

    public const TICKET_READ = 'intendance.ticket.read';
    public const TICKET_CREATE = 'intendance.ticket.create';
    public const TICKET_PRENDRE = 'intendance.ticket.prendre';
    public const TICKET_UPDATE = 'intendance.ticket.update';
    public const TICKET_CLOTURER = 'intendance.ticket.cloturer';

    /**
     * @return list<array{code: string, libelle: string, module: string}>
     */
    public static function allDefinitions(): array
    {
        return array_merge(
            self::crud('famille', 'une famille de bien', 'les familles de bien'),
            self::crud('type', 'un type de bien', 'les types de bien'),
            self::crud('local', 'un local', 'les locaux'),
            self::crud('bien', 'un bien patrimonial', 'les biens patrimoniaux'),
            [
                [
                    'code' => self::BIEN_EXPORT,
                    'libelle' => 'Exporter le parc (liste des biens)',
                    'module' => Permission::MODULE_INTENDANCE,
                ],
                [
                    'code' => self::ETIQUETTE_GENERATE,
                    'libelle' => 'Générer les étiquettes du parc',
                    'module' => Permission::MODULE_INTENDANCE,
                ],
                [
                    'code' => self::SYNTHESE_READ,
                    'libelle' => 'Consulter les effectifs et la synthèse Intendance',
                    'module' => Permission::MODULE_INTENDANCE,
                ],
                [
                    'code' => self::CAMPAGNE_READ,
                    'libelle' => 'Consulter les campagnes d’inventaire',
                    'module' => Permission::MODULE_INTENDANCE,
                ],
                [
                    'code' => self::CAMPAGNE_CREATE,
                    'libelle' => 'Créer une campagne d’inventaire',
                    'module' => Permission::MODULE_INTENDANCE,
                ],
                [
                    'code' => self::CAMPAGNE_UPDATE,
                    'libelle' => 'Ouvrir une campagne et saisir les constats',
                    'module' => Permission::MODULE_INTENDANCE,
                ],
                [
                    'code' => self::CAMPAGNE_VISER,
                    'libelle' => 'Viser le recensement de son service',
                    'module' => Permission::MODULE_INTENDANCE,
                ],
                [
                    'code' => self::CAMPAGNE_VISER_TOUS,
                    'libelle' => 'Viser le recensement de n’importe quel service',
                    'module' => Permission::MODULE_INTENDANCE,
                ],
                [
                    'code' => self::CAMPAGNE_CLOTURER,
                    'libelle' => 'Clôturer une campagne d’inventaire',
                    'module' => Permission::MODULE_INTENDANCE,
                ],
                [
                    'code' => self::TICKET_READ,
                    'libelle' => 'Consulter les tickets d’anomalie',
                    'module' => Permission::MODULE_INTENDANCE,
                ],
                [
                    'code' => self::TICKET_CREATE,
                    'libelle' => 'Signaler une anomalie',
                    'module' => Permission::MODULE_INTENDANCE,
                ],
                [
                    'code' => self::TICKET_PRENDRE,
                    'libelle' => 'Prendre en charge un ticket d’anomalie',
                    'module' => Permission::MODULE_INTENDANCE,
                ],
                [
                    'code' => self::TICKET_UPDATE,
                    'libelle' => 'Mettre à jour un ticket d’anomalie',
                    'module' => Permission::MODULE_INTENDANCE,
                ],
                [
                    'code' => self::TICKET_CLOTURER,
                    'libelle' => 'Clôturer un ticket d’anomalie',
                    'module' => Permission::MODULE_INTENDANCE,
                ],
            ],
        );
    }

    /**
     * @return list<array{code: string, libelle: string, module: string}>
     */
    private static function crud(string $resource, string $singular, string $pluralLabel): array
    {
        $prefix = 'intendance.' . $resource;

        return [
            [
                'code' => $prefix . '.read',
                'libelle' => 'Lire ' . $pluralLabel,
                'module' => Permission::MODULE_INTENDANCE,
            ],
            [
                'code' => $prefix . '.create',
                'libelle' => 'Créer ' . $singular,
                'module' => Permission::MODULE_INTENDANCE,
            ],
            [
                'code' => $prefix . '.update',
                'libelle' => 'Modifier ' . $singular,
                'module' => Permission::MODULE_INTENDANCE,
            ],
            [
                'code' => $prefix . '.delete',
                'libelle' => 'Supprimer ' . $singular,
                'module' => Permission::MODULE_INTENDANCE,
            ],
        ];
    }
}
