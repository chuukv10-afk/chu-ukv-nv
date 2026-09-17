<?php

namespace App\Referentiel;

/**
 * Catalogue officiel issu de l'état de paie (prime locale août 2026).
 * Les abréviations Excel sont développées ; « Cheffe de service » rejoint « Chef de service ».
 */
final class FonctionCatalog
{
    /**
     * @return list<array{code: string, libelle: string, serviceCode: string|null}>
     */
    public static function definitions(): array
    {
        return [
            ['code' => 'AGTITUL', 'libelle' => 'Administrateur gestionnaire titulaire', 'serviceCode' => 'DIRADM'],
            ['code' => 'AGFIN', 'libelle' => 'Administrateur gestionnaire finances', 'serviceCode' => 'DIRF'],
            ['code' => 'ARCHIV', 'libelle' => 'Archiviste', 'serviceCode' => 'DIRADM'],
            ['code' => 'CAISSIERE', 'libelle' => 'Caissière', 'serviceCode' => 'DIRF'],
            ['code' => 'CHEFDEPT', 'libelle' => 'Chef de département', 'serviceCode' => null],
            ['code' => 'CHEFPERS', 'libelle' => 'Chef du personnel', 'serviceCode' => 'DIRADM'],
            ['code' => 'CHEFLABO', 'libelle' => 'Chef de service laboratoire', 'serviceCode' => 'LABO'],
            ['code' => 'CHEFSAD', 'libelle' => 'Chef de service administratif', 'serviceCode' => 'DIRADM'],
            ['code' => 'CHEFSVC', 'libelle' => 'Chef de service', 'serviceCode' => null],
            ['code' => 'COMPTABLE', 'libelle' => 'Comptable', 'serviceCode' => 'DIRF'],
            ['code' => 'DN', 'libelle' => 'Directeur des soins infirmiers', 'serviceCode' => 'INFIRMIE'],
            ['code' => 'DNA', 'libelle' => 'Directeur des soins infirmiers adjoint', 'serviceCode' => 'INFIRMIE'],
            ['code' => 'FACTURIER', 'libelle' => 'Facturier', 'serviceCode' => 'DIRF'],
            ['code' => 'IMAGERIE', 'libelle' => 'Imagerie', 'serviceCode' => 'IMG'],
            ['code' => 'INFANES', 'libelle' => 'Infirmier anesthésiste', 'serviceCode' => null],
            ['code' => 'INFTRAIT', 'libelle' => 'Infirmier traitant', 'serviceCode' => null],
            ['code' => 'INTENDANT', 'libelle' => 'Intendant', 'serviceCode' => 'LOGMAINT'],
            ['code' => 'MAINTEN', 'libelle' => 'Maintenancier', 'serviceCode' => 'LOGMAINT'],
            ['code' => 'MDA1', 'libelle' => 'Médecin directeur adjoint 1', 'serviceCode' => null],
            ['code' => 'MDA2', 'libelle' => 'Médecin directeur adjoint 2', 'serviceCode' => null],
            ['code' => 'MDTITUL', 'libelle' => 'Médecin directeur titulaire', 'serviceCode' => null],
            ['code' => 'MEDTRAIT', 'libelle' => 'Médecin traitant', 'serviceCode' => null],
            ['code' => 'PHARMA', 'libelle' => 'Pharmacien', 'serviceCode' => 'PHAR'],
            ['code' => 'SAGEFEM', 'libelle' => 'Sage-femme', 'serviceCode' => 'GYNECO'],
            ['code' => 'SECRET', 'libelle' => 'Secrétaire', 'serviceCode' => 'DIRADM'],
            ['code' => 'STAGLABO', 'libelle' => 'Stagiaire laboratoire', 'serviceCode' => 'LABO'],
            ['code' => 'STAGMI', 'libelle' => 'Stagiaire médecine interne', 'serviceCode' => 'MI'],
            ['code' => 'STAGPED', 'libelle' => 'Stagiaire pédiatrie', 'serviceCode' => 'PED'],
            ['code' => 'STAGURG', 'libelle' => 'Stagiaire urgences', 'serviceCode' => 'URG'],
            ['code' => 'TECHLABO', 'libelle' => 'Technicien de laboratoire', 'serviceCode' => 'LABO'],
            ['code' => 'TECHSURF', 'libelle' => 'Technicien de surface', 'serviceCode' => 'ENT'],
        ];
    }
}
