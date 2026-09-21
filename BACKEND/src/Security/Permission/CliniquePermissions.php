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

    public const APTITUDE_READ = 'clinique.aptitude.read';
    public const APTITUDE_CREATE = 'clinique.aptitude.create';
    public const APTITUDE_UPDATE = 'clinique.aptitude.update';
    public const APTITUDE_DELETE = 'clinique.aptitude.delete';
    public const APTITUDE_DELETE_DEFINITIF = 'clinique.aptitude.delete_definitif';
    public const APTITUDE_SIGN = 'clinique.aptitude.sign';
    public const APTITUDE_UPDATE_SIGNE = 'clinique.aptitude.update_signe';
    public const APTITUDE_EXPORT = 'clinique.aptitude.export';
    public const APTITUDE_IDENTITE_READ = 'clinique.aptitude.identite.read';
    public const APTITUDE_IDENTITE_UPDATE = 'clinique.aptitude.identite.update';
    public const APTITUDE_IMC_READ = 'clinique.aptitude.imc.read';
    public const APTITUDE_IMC_UPDATE = 'clinique.aptitude.imc.update';
    public const APTITUDE_PIGNET_READ = 'clinique.aptitude.pignet.read';
    public const APTITUDE_PIGNET_UPDATE = 'clinique.aptitude.pignet.update';
    public const APTITUDE_RUFFIER_READ = 'clinique.aptitude.ruffier.read';
    public const APTITUDE_RUFFIER_UPDATE = 'clinique.aptitude.ruffier.update';
    public const APTITUDE_VERDICT_READ = 'clinique.aptitude.verdict.read';
    public const APTITUDE_VERDICT_UPDATE = 'clinique.aptitude.verdict.update';

    public const IMAGERIE_READ = 'clinique.imagerie.read';
    public const IMAGERIE_CREATE = 'clinique.imagerie.create';
    public const IMAGERIE_UPDATE = 'clinique.imagerie.update';
    public const IMAGERIE_DELETE = 'clinique.imagerie.delete';
    public const IMAGERIE_UPLOAD = 'clinique.imagerie.upload';
    public const IMAGERIE_INTERPRET = 'clinique.imagerie.interpret';
    public const IMAGERIE_VALIDATE = 'clinique.imagerie.validate';
    public const IMAGERIE_EXPORT = 'clinique.imagerie.export';

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
            self::crud('aptitude', 'certificat d\'aptitude physique', 'les certificats d\'aptitude physique'),
            [
                [
                    'code' => self::APTITUDE_IDENTITE_READ,
                    'libelle' => 'Aptitude — voir l\'identité du candidat',
                    'module' => Permission::MODULE_CLINIQUE,
                ],
                [
                    'code' => self::APTITUDE_IDENTITE_UPDATE,
                    'libelle' => 'Aptitude — modifier l\'identité du candidat',
                    'module' => Permission::MODULE_CLINIQUE,
                ],
                [
                    'code' => self::APTITUDE_IMC_READ,
                    'libelle' => 'Aptitude — voir l\'IMC',
                    'module' => Permission::MODULE_CLINIQUE,
                ],
                [
                    'code' => self::APTITUDE_IMC_UPDATE,
                    'libelle' => 'Aptitude — saisir / modifier l\'IMC',
                    'module' => Permission::MODULE_CLINIQUE,
                ],
                [
                    'code' => self::APTITUDE_PIGNET_READ,
                    'libelle' => 'Aptitude — voir l\'indice de Pignet',
                    'module' => Permission::MODULE_CLINIQUE,
                ],
                [
                    'code' => self::APTITUDE_PIGNET_UPDATE,
                    'libelle' => 'Aptitude — saisir / modifier l\'indice de Pignet',
                    'module' => Permission::MODULE_CLINIQUE,
                ],
                [
                    'code' => self::APTITUDE_RUFFIER_READ,
                    'libelle' => 'Aptitude — voir l\'indice de Ruffier-Dickson',
                    'module' => Permission::MODULE_CLINIQUE,
                ],
                [
                    'code' => self::APTITUDE_RUFFIER_UPDATE,
                    'libelle' => 'Aptitude — saisir / modifier l\'indice de Ruffier-Dickson',
                    'module' => Permission::MODULE_CLINIQUE,
                ],
                [
                    'code' => self::APTITUDE_VERDICT_READ,
                    'libelle' => 'Aptitude — voir le verdict médical',
                    'module' => Permission::MODULE_CLINIQUE,
                ],
                [
                    'code' => self::APTITUDE_VERDICT_UPDATE,
                    'libelle' => 'Aptitude — modifier le verdict médical',
                    'module' => Permission::MODULE_CLINIQUE,
                ],
                [
                    'code' => self::APTITUDE_DELETE_DEFINITIF,
                    'libelle' => 'Supprimer définitivement un certificat d\'aptitude (signé ou annulé compris)',
                    'module' => Permission::MODULE_CLINIQUE,
                ],
                [
                    'code' => self::APTITUDE_SIGN,
                    'libelle' => 'Signer un certificat d\'aptitude physique',
                    'module' => Permission::MODULE_CLINIQUE,
                ],
                [
                    'code' => self::APTITUDE_UPDATE_SIGNE,
                    'libelle' => 'Modifier une attestation d\'aptitude déjà signée',
                    'module' => Permission::MODULE_CLINIQUE,
                ],
                [
                    'code' => self::APTITUDE_EXPORT,
                    'libelle' => 'Exporter / imprimer un certificat d\'aptitude physique',
                    'module' => Permission::MODULE_CLINIQUE,
                ],
            ],
            [
                [
                    'code' => self::IMAGERIE_READ,
                    'libelle' => 'Consulter le journal d\'imagerie',
                    'module' => Permission::MODULE_CLINIQUE,
                ],
                [
                    'code' => self::IMAGERIE_CREATE,
                    'libelle' => 'Créer une étude d\'imagerie',
                    'module' => Permission::MODULE_CLINIQUE,
                ],
                [
                    'code' => self::IMAGERIE_UPDATE,
                    'libelle' => 'Modifier une étude d\'imagerie',
                    'module' => Permission::MODULE_CLINIQUE,
                ],
                [
                    'code' => self::IMAGERIE_DELETE,
                    'libelle' => 'Supprimer une étude d\'imagerie',
                    'module' => Permission::MODULE_CLINIQUE,
                ],
                [
                    'code' => self::IMAGERIE_UPLOAD,
                    'libelle' => 'Téléverser des images médicales',
                    'module' => Permission::MODULE_CLINIQUE,
                ],
                [
                    'code' => self::IMAGERIE_INTERPRET,
                    'libelle' => 'Voir et rédiger l\'interprétation d\'imagerie',
                    'module' => Permission::MODULE_CLINIQUE,
                ],
                [
                    'code' => self::IMAGERIE_VALIDATE,
                    'libelle' => 'Valider un compte-rendu d\'imagerie',
                    'module' => Permission::MODULE_CLINIQUE,
                ],
                [
                    'code' => self::IMAGERIE_EXPORT,
                    'libelle' => 'Imprimer un compte-rendu d\'imagerie',
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
