<?php

namespace App\Security\Permission;

use App\Entity\Permission;

/**
 * Permissions du module Pharmacie.
 */
final class PharmaciePermissions
{
    public const UNITE_READ = 'pharmacie.unite.read';
    public const UNITE_CREATE = 'pharmacie.unite.create';
    public const UNITE_UPDATE = 'pharmacie.unite.update';
    public const UNITE_DELETE = 'pharmacie.unite.delete';

    public const FAMILLE_READ = 'pharmacie.famille.read';
    public const FAMILLE_CREATE = 'pharmacie.famille.create';
    public const FAMILLE_UPDATE = 'pharmacie.famille.update';
    public const FAMILLE_DELETE = 'pharmacie.famille.delete';

    public const MEDICAMENT_READ = 'pharmacie.medicament.read';
    public const MEDICAMENT_CREATE = 'pharmacie.medicament.create';
    public const MEDICAMENT_UPDATE = 'pharmacie.medicament.update';
    public const MEDICAMENT_DELETE = 'pharmacie.medicament.delete';
    public const MEDICAMENT_EXPORT = 'pharmacie.medicament.export';

    public const FOURNISSEUR_READ = 'pharmacie.fournisseur.read';
    public const FOURNISSEUR_CREATE = 'pharmacie.fournisseur.create';
    public const FOURNISSEUR_UPDATE = 'pharmacie.fournisseur.update';
    public const FOURNISSEUR_DELETE = 'pharmacie.fournisseur.delete';

    public const RECEPTION_READ = 'pharmacie.reception.read';
    public const RECEPTION_CREATE = 'pharmacie.reception.create';
    public const RECEPTION_UPDATE = 'pharmacie.reception.update';
    public const RECEPTION_DELETE = 'pharmacie.reception.delete';
    public const RECEPTION_VALIDER = 'pharmacie.reception.valider';

    public const LOT_READ = 'pharmacie.lot.read';
    public const MOUVEMENT_READ = 'pharmacie.mouvement.read';

    public const VENTE_READ = 'pharmacie.vente.read';
    public const VENTE_CREATE = 'pharmacie.vente.create';
    public const VENTE_UPDATE = 'pharmacie.vente.update';
    public const VENTE_DELETE = 'pharmacie.vente.delete';
    public const VENTE_VALIDER = 'pharmacie.vente.valider';
    public const VENTE_ANNULER = 'pharmacie.vente.annuler';
    public const VENTE_ANNULER_HORS_DELAI = 'pharmacie.vente.annuler_hors_delai';

    public const DEMANDE_SERVICE_READ = 'pharmacie.demande_service.read';
    public const DEMANDE_SERVICE_CREATE = 'pharmacie.demande_service.create';
    public const DEMANDE_SERVICE_UPDATE = 'pharmacie.demande_service.update';
    public const DEMANDE_SERVICE_DELETE = 'pharmacie.demande_service.delete';
    public const DEMANDE_SERVICE_ENVOYER = 'pharmacie.demande_service.envoyer';
    public const DEMANDE_SERVICE_DELIVRER = 'pharmacie.demande_service.delivrer';
    public const DEMANDE_SERVICE_REFUSER = 'pharmacie.demande_service.refuser';
    public const DEMANDE_SERVICE_REGLER = 'pharmacie.demande_service.regler';

    public const AJUSTEMENT_CREATE = 'pharmacie.ajustement.create';
    public const RECETTE_READ = 'pharmacie.recette.read';
    public const STATISTIQUE_READ = 'pharmacie.statistique.read';
    public const MOUVEMENT_EXPORT = 'pharmacie.mouvement.export';

    /**
     * @return list<array{code: string, libelle: string, module: string}>
     */
    public static function allDefinitions(): array
    {
        return array_merge(
            self::crud('unite', 'une unité de médicament', 'les unités de médicament'),
            self::crud('famille', 'une famille de médicament', 'les familles de médicament'),
            self::crud('medicament', 'un médicament', 'les médicaments'),
            [
                [
                    'code' => self::MEDICAMENT_EXPORT,
                    'libelle' => 'Exporter les médicaments (PDF / Excel)',
                    'module' => Permission::MODULE_PHARMACIE,
                ],
            ],
            self::crud('fournisseur', 'un fournisseur', 'les fournisseurs'),
            self::crud('reception', 'une réception', 'les réceptions'),
            [
                [
                    'code' => self::RECEPTION_VALIDER,
                    'libelle' => 'Valider une réception (entrée en stock)',
                    'module' => Permission::MODULE_PHARMACIE,
                ],
                [
                    'code' => self::LOT_READ,
                    'libelle' => 'Consulter les lots et péremptions',
                    'module' => Permission::MODULE_PHARMACIE,
                ],
                [
                    'code' => self::MOUVEMENT_READ,
                    'libelle' => 'Consulter le journal de stock',
                    'module' => Permission::MODULE_PHARMACIE,
                ],
                [
                    'code' => self::MOUVEMENT_EXPORT,
                    'libelle' => 'Générer les fiches de stock (PDF / Excel)',
                    'module' => Permission::MODULE_PHARMACIE,
                ],
            ],
            self::crud('vente', 'une vente', 'les ventes'),
            [
                [
                    'code' => self::VENTE_VALIDER,
                    'libelle' => 'Valider une vente (sortie de stock)',
                    'module' => Permission::MODULE_PHARMACIE,
                ],
                [
                    'code' => self::VENTE_ANNULER,
                    'libelle' => 'Annuler une vente le jour même',
                    'module' => Permission::MODULE_PHARMACIE,
                ],
                [
                    'code' => self::VENTE_ANNULER_HORS_DELAI,
                    'libelle' => 'Annuler une vente après le jour J',
                    'module' => Permission::MODULE_PHARMACIE,
                ],
            ],
            self::crud('demande_service', 'une demande de service', 'les demandes de service'),
            [
                [
                    'code' => self::DEMANDE_SERVICE_ENVOYER,
                    'libelle' => 'Envoyer une demande de service',
                    'module' => Permission::MODULE_PHARMACIE,
                ],
                [
                    'code' => self::DEMANDE_SERVICE_DELIVRER,
                    'libelle' => 'Délivrer une demande de service',
                    'module' => Permission::MODULE_PHARMACIE,
                ],
                [
                    'code' => self::DEMANDE_SERVICE_REFUSER,
                    'libelle' => 'Refuser une demande de service',
                    'module' => Permission::MODULE_PHARMACIE,
                ],
                [
                    'code' => self::DEMANDE_SERVICE_REGLER,
                    'libelle' => 'Régler une créance service',
                    'module' => Permission::MODULE_PHARMACIE,
                ],
                [
                    'code' => self::AJUSTEMENT_CREATE,
                    'libelle' => 'Créer un ajustement de stock',
                    'module' => Permission::MODULE_PHARMACIE,
                ],
                [
                    'code' => self::RECETTE_READ,
                    'libelle' => 'Consulter le suivi des recettes (ventes et services)',
                    'module' => Permission::MODULE_PHARMACIE,
                ],
                [
                    'code' => self::STATISTIQUE_READ,
                    'libelle' => 'Consulter les statistiques pharmacie',
                    'module' => Permission::MODULE_PHARMACIE,
                ],
            ],
        );
    }

    /**
     * @return list<array{code: string, libelle: string, module: string}>
     */
    private static function crud(string $resource, string $singular, string $pluralLabel): array
    {
        $prefix = 'pharmacie.' . $resource;

        return [
            [
                'code' => $prefix . '.read',
                'libelle' => 'Lire ' . $pluralLabel,
                'module' => Permission::MODULE_PHARMACIE,
            ],
            [
                'code' => $prefix . '.create',
                'libelle' => 'Créer ' . $singular,
                'module' => Permission::MODULE_PHARMACIE,
            ],
            [
                'code' => $prefix . '.update',
                'libelle' => 'Modifier ' . $singular,
                'module' => Permission::MODULE_PHARMACIE,
            ],
            [
                'code' => $prefix . '.delete',
                'libelle' => 'Supprimer ' . $singular,
                'module' => Permission::MODULE_PHARMACIE,
            ],
        ];
    }
}
