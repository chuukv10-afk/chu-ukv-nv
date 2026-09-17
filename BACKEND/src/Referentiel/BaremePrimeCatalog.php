<?php

namespace App\Referentiel;

/**
 * Barème de prime locale déduit de l'état Excel (août 2026).
 * Montant = mode observé pour le couple grade + fonction.
 */
final class BaremePrimeCatalog
{
    /**
     * @return list<array{gradeCode: ?string, gradeLibelle: string, fonctionCode: string, montant: string}>
     */
    public static function definitions(): array
    {
        return [
            ['gradeCode' => 'AGA1', 'gradeLibelle' => 'AGA 1', 'fonctionCode' => 'TECHSURF', 'montant' => '96201.00'],
            ['gradeCode' => 'AGA2', 'gradeLibelle' => 'AGA 2', 'fonctionCode' => 'TECHSURF', 'montant' => '85512.00'],
            ['gradeCode' => 'AGB1', 'gradeLibelle' => 'AGB 1', 'fonctionCode' => 'INFTRAIT', 'montant' => '130000.00'],
            ['gradeCode' => 'AGB1', 'gradeLibelle' => 'AGB 1', 'fonctionCode' => 'TECHSURF', 'montant' => '96201.00'],
            ['gradeCode' => 'AGB2', 'gradeLibelle' => 'AGB 2', 'fonctionCode' => 'SAGEFEM', 'montant' => '120000.00'],
            ['gradeCode' => 'ATA1', 'gradeLibelle' => 'ATA 1', 'fonctionCode' => 'MEDTRAIT', 'montant' => '165000.00'],
            ['gradeCode' => 'ATA1', 'gradeLibelle' => 'ATA 1', 'fonctionCode' => 'AGTITUL', 'montant' => '300000.00'],
            ['gradeCode' => 'ATA1', 'gradeLibelle' => 'ATA 1', 'fonctionCode' => 'ARCHIV', 'montant' => '112234.00'],
            ['gradeCode' => 'ATA1', 'gradeLibelle' => 'ATA 1', 'fonctionCode' => 'FACTURIER', 'montant' => '112234.00'],
            ['gradeCode' => 'ATA2', 'gradeLibelle' => 'ATA 2', 'fonctionCode' => 'TECHLABO', 'montant' => '106890.00'],
            ['gradeCode' => 'ATA2', 'gradeLibelle' => 'ATA 2', 'fonctionCode' => 'IMAGERIE', 'montant' => '130000.00'],
            ['gradeCode' => 'ATA2', 'gradeLibelle' => 'ATA 2', 'fonctionCode' => 'INFTRAIT', 'montant' => '130000.00'],
            ['gradeCode' => 'ATA2', 'gradeLibelle' => 'ATA 2', 'fonctionCode' => 'CAISSIERE', 'montant' => '106890.00'],
            ['gradeCode' => 'ATA2', 'gradeLibelle' => 'ATA 2', 'fonctionCode' => 'COMPTABLE', 'montant' => '106890.00'],
            ['gradeCode' => 'ATA2', 'gradeLibelle' => 'ATA 2', 'fonctionCode' => 'SECRET', 'montant' => '140000.00'],
            ['gradeCode' => 'ATA2', 'gradeLibelle' => 'ATA 2', 'fonctionCode' => 'CHEFSAD', 'montant' => '140000.00'],
            ['gradeCode' => 'ATA1', 'gradeLibelle' => 'ATA 1', 'fonctionCode' => 'CHEFSVC', 'montant' => '106890.00'],
            ['gradeCode' => 'ATA2', 'gradeLibelle' => 'ATA 2', 'fonctionCode' => 'CHEFSVC', 'montant' => '106890.00'],
            ['gradeCode' => 'ATB2', 'gradeLibelle' => 'ATB 2', 'fonctionCode' => 'INFTRAIT', 'montant' => '106890.00'],
            ['gradeCode' => 'ATB2', 'gradeLibelle' => 'ATB 2', 'fonctionCode' => 'CHEFSVC', 'montant' => '106890.00'],
            ['gradeCode' => 'ATB2', 'gradeLibelle' => 'ATB 2', 'fonctionCode' => 'DN', 'montant' => '150000.00'],
            ['gradeCode' => 'ATB2', 'gradeLibelle' => 'ATB 2', 'fonctionCode' => 'DNA', 'montant' => '106890.00'],
            ['gradeCode' => 'ATB2', 'gradeLibelle' => 'ATB 2', 'fonctionCode' => 'INFANES', 'montant' => '130000.00'],
            ['gradeCode' => 'ASSIST', 'gradeLibelle' => 'Assistant', 'fonctionCode' => 'MEDTRAIT', 'montant' => '150000.00'],
            ['gradeCode' => 'PROF', 'gradeLibelle' => 'Professeur', 'fonctionCode' => 'MEDTRAIT', 'montant' => '170000.00'],
            ['gradeCode' => 'PROF', 'gradeLibelle' => 'Professeur', 'fonctionCode' => 'CHEFSVC', 'montant' => '170000.00'],
            ['gradeCode' => 'PROF', 'gradeLibelle' => 'Professeur', 'fonctionCode' => 'MDTITUL', 'montant' => '350000.00'],
            ['gradeCode' => 'PROF', 'gradeLibelle' => 'Professeur', 'fonctionCode' => 'MDA1', 'montant' => '170000.00'],
            ['gradeCode' => 'PROFASS', 'gradeLibelle' => 'Professeur associé', 'fonctionCode' => 'CHEFDEPT', 'montant' => '170000.00'],
            ['gradeCode' => 'PROFASS', 'gradeLibelle' => 'Professeur associé', 'fonctionCode' => 'MDA2', 'montant' => '170000.00'],
            ['gradeCode' => 'CB', 'gradeLibelle' => 'C.B', 'fonctionCode' => 'INFTRAIT', 'montant' => '138957.00'],
            ['gradeCode' => 'CHTRAV', 'gradeLibelle' => 'Chef de travaux', 'fonctionCode' => 'MEDTRAIT', 'montant' => '150000.00'],
            ['gradeCode' => 'DIR', 'gradeLibelle' => 'Directeur', 'fonctionCode' => 'CHEFPERS', 'montant' => '201587.00'],
            ['gradeCode' => 'INFA1', 'gradeLibelle' => 'INF. A1', 'fonctionCode' => 'STAGLABO', 'montant' => '40000.00'],
            ['gradeCode' => 'INFA1', 'gradeLibelle' => 'INF. A1', 'fonctionCode' => 'STAGMI', 'montant' => '40000.00'],
            ['gradeCode' => 'INFA1', 'gradeLibelle' => 'INF. A1', 'fonctionCode' => 'STAGPED', 'montant' => '40000.00'],
            ['gradeCode' => 'INFA1', 'gradeLibelle' => 'INF. A1', 'fonctionCode' => 'STAGURG', 'montant' => '40000.00'],
            ['gradeCode' => 'INFA1N', 'gradeLibelle' => 'INF. A1 Nutri', 'fonctionCode' => 'STAGPED', 'montant' => '40000.00'],
            ['gradeCode' => null, 'gradeLibelle' => '', 'fonctionCode' => 'PHARMA', 'montant' => '230000.00'],
        ];
    }

    /**
     * @return array<string, string> normalized excel label => grade code
     */
    public static function gradeAliases(): array
    {
        return [
            'aga1' => 'AGA1',
            'aga 1' => 'AGA1',
            'aga2' => 'AGA2',
            'aga 2' => 'AGA2',
            'agb1' => 'AGB1',
            'agb 1' => 'AGB1',
            'agb2' => 'AGB2',
            'agb 2' => 'AGB2',
            'ata1' => 'ATA1',
            'ata 1' => 'ATA1',
            'ata2' => 'ATA2',
            'ata 2' => 'ATA2',
            'atb2' => 'ATB2',
            'atb 2' => 'ATB2',
            'assistant' => 'ASSIST',
            'assistante' => 'ASSIST',
            'assistante 1' => 'ASSIST',
            'professeur' => 'PROF',
            'prof associe' => 'PROFASS',
            'prof associé' => 'PROFASS',
            'c.b' => 'CB',
            'cb' => 'CB',
            'chef de travaux' => 'CHTRAV',
            'directeur' => 'DIR',
            'inf. a1' => 'INFA1',
            'inf a1' => 'INFA1',
            'inf. a1 nutri' => 'INFA1N',
        ];
    }
}
