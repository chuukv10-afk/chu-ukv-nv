<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Types\IntegerType;
use Doctrine\Migrations\AbstractMigration;

final class Version20260918090000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Organisations partenaires (type d\'institution), rattachement des filières, suivi d\'impression des certificats d\'aptitude.';
    }

    public function up(Schema $schema): void
    {
        if (!$schema->hasTable('organisation_partenaire')) {
            $this->addSql('CREATE TABLE organisation_partenaire (
                id INT AUTO_INCREMENT NOT NULL,
                code VARCHAR(15) NOT NULL,
                libelle VARCHAR(150) NOT NULL,
                type_institution VARCHAR(30) NOT NULL,
                statut VARCHAR(20) NOT NULL,
                created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\',
                UNIQUE INDEX UNIQ_ORG_PARTENAIRE_CODE (code),
                PRIMARY KEY(id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        }

        $this->addSql("INSERT INTO organisation_partenaire (code, libelle, type_institution, statut, created_at)
            SELECT 'UKV', 'Université Kongo Vivi', 'UNIVERSITE', 'ACTIF', '2026-09-18 00:00:00'
            WHERE NOT EXISTS (SELECT 1 FROM organisation_partenaire WHERE code = 'UKV')");

        if ($schema->hasTable('filiere')) {
            $filiere = $schema->getTable('filiere');
            if (!$filiere->hasColumn('organisation_id')) {
                $this->addSql('ALTER TABLE filiere ADD organisation_id INT DEFAULT NULL');
            }
            if (!$this->hasIndexOnColumn($filiere, 'organisation_id')) {
                $this->addSql('CREATE INDEX IDX_FILIERE_ORGANISATION ON filiere (organisation_id)');
            }
            if (!$this->hasForeignKeyOnColumn($filiere, 'organisation_id')) {
                $this->addSql('ALTER TABLE filiere ADD CONSTRAINT FK_FILIERE_ORGANISATION FOREIGN KEY (organisation_id) REFERENCES organisation_partenaire (id) ON DELETE RESTRICT');
            }
            $this->addSql("UPDATE filiere SET organisation_id = (SELECT id FROM organisation_partenaire WHERE code = 'UKV') WHERE organisation_id IS NULL");
        }

        if ($schema->hasTable('patient')) {
            $patient = $schema->getTable('patient');
            if (!$patient->hasColumn('organisation_id')) {
                $this->addSql('ALTER TABLE patient ADD organisation_id INT DEFAULT NULL');
            }
            if (!$this->hasIndexOnColumn($patient, 'organisation_id')) {
                $this->addSql('CREATE INDEX IDX_PATIENT_ORGANISATION ON patient (organisation_id)');
            }
            if (!$this->hasForeignKeyOnColumn($patient, 'organisation_id')) {
                $this->addSql('ALTER TABLE patient ADD CONSTRAINT FK_PATIENT_ORGANISATION FOREIGN KEY (organisation_id) REFERENCES organisation_partenaire (id) ON DELETE SET NULL');
            }
            $this->addSql('UPDATE patient p INNER JOIN filiere f ON p.filiere_id = f.id SET p.organisation_id = f.organisation_id WHERE p.organisation_id IS NULL');
        }

        if ($schema->hasTable('certificat_aptitude')) {
            $certificat = $schema->getTable('certificat_aptitude');
            if (!$certificat->hasColumn('imprime')) {
                $this->addSql('ALTER TABLE certificat_aptitude ADD imprime TINYINT(1) NOT NULL DEFAULT 0');
            }
            if (!$certificat->hasColumn('imprime_at')) {
                $this->addSql('ALTER TABLE certificat_aptitude ADD imprime_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\'');
            }
            if (!$certificat->hasColumn('imprime_par_id')) {
                $this->addSql('ALTER TABLE certificat_aptitude ADD imprime_par_id BINARY(16) DEFAULT NULL COMMENT \'(DC2Type:uuid)\'');
            } elseif ($certificat->getColumn('imprime_par_id')->getType() instanceof IntegerType) {
                $this->addSql('ALTER TABLE certificat_aptitude MODIFY imprime_par_id BINARY(16) DEFAULT NULL COMMENT \'(DC2Type:uuid)\'');
            }
            if (!$this->hasIndexOnColumn($certificat, 'imprime')) {
                $this->addSql('CREATE INDEX IDX_CAP_IMPRIME ON certificat_aptitude (imprime)');
            }
            if (!$this->hasIndexOnColumn($certificat, 'imprime_par_id')) {
                $this->addSql('CREATE INDEX IDX_CAP_IMPRIME_PAR ON certificat_aptitude (imprime_par_id)');
            }
            if (!$this->hasForeignKeyOnColumn($certificat, 'imprime_par_id')) {
                $this->addSql('ALTER TABLE certificat_aptitude ADD CONSTRAINT FK_CAP_IMPRIME_PAR FOREIGN KEY (imprime_par_id) REFERENCES personnel (id) ON DELETE SET NULL');
            }
        }
    }

    public function down(Schema $schema): void
    {
        if ($schema->hasTable('certificat_aptitude')) {
            $certificat = $schema->getTable('certificat_aptitude');
            $fk = $this->foreignKeyNameOnColumn($certificat, 'imprime_par_id');
            if (null !== $fk) {
                $this->addSql('ALTER TABLE certificat_aptitude DROP FOREIGN KEY ' . $fk);
            }
            $indexImprime = $this->indexNameOnColumn($certificat, 'imprime');
            if (null !== $indexImprime) {
                $this->addSql('DROP INDEX ' . $indexImprime . ' ON certificat_aptitude');
            }
            $indexImprimePar = $this->indexNameOnColumn($certificat, 'imprime_par_id');
            if (null !== $indexImprimePar) {
                $this->addSql('DROP INDEX ' . $indexImprimePar . ' ON certificat_aptitude');
            }
            $drops = [];
            if ($certificat->hasColumn('imprime')) {
                $drops[] = 'imprime';
            }
            if ($certificat->hasColumn('imprime_at')) {
                $drops[] = 'imprime_at';
            }
            if ($certificat->hasColumn('imprime_par_id')) {
                $drops[] = 'imprime_par_id';
            }
            if ([] !== $drops) {
                $this->addSql('ALTER TABLE certificat_aptitude DROP ' . implode(', DROP ', $drops));
            }
        }

        if ($schema->hasTable('patient')) {
            $patient = $schema->getTable('patient');
            $fk = $this->foreignKeyNameOnColumn($patient, 'organisation_id');
            if (null !== $fk) {
                $this->addSql('ALTER TABLE patient DROP FOREIGN KEY ' . $fk);
            }
            $index = $this->indexNameOnColumn($patient, 'organisation_id');
            if (null !== $index) {
                $this->addSql('DROP INDEX ' . $index . ' ON patient');
            }
            if ($patient->hasColumn('organisation_id')) {
                $this->addSql('ALTER TABLE patient DROP organisation_id');
            }
        }

        if ($schema->hasTable('filiere')) {
            $filiere = $schema->getTable('filiere');
            $fk = $this->foreignKeyNameOnColumn($filiere, 'organisation_id');
            if (null !== $fk) {
                $this->addSql('ALTER TABLE filiere DROP FOREIGN KEY ' . $fk);
            }
            $index = $this->indexNameOnColumn($filiere, 'organisation_id');
            if (null !== $index) {
                $this->addSql('DROP INDEX ' . $index . ' ON filiere');
            }
            if ($filiere->hasColumn('organisation_id')) {
                $this->addSql('ALTER TABLE filiere DROP organisation_id');
            }
        }

        if ($schema->hasTable('organisation_partenaire')) {
            $this->addSql('DROP TABLE organisation_partenaire');
        }
    }

    private function hasIndexOnColumn(Table $table, string $column): bool
    {
        return null !== $this->indexNameOnColumn($table, $column);
    }

    private function hasForeignKeyOnColumn(Table $table, string $column): bool
    {
        return null !== $this->foreignKeyNameOnColumn($table, $column);
    }

    private function indexNameOnColumn(Table $table, string $column): ?string
    {
        foreach ($table->getIndexes() as $index) {
            if ($index->isPrimary()) {
                continue;
            }
            if ($index->getColumns() === [$column]) {
                return $index->getName();
            }
        }

        return null;
    }

    private function foreignKeyNameOnColumn(Table $table, string $column): ?string
    {
        foreach ($table->getForeignKeys() as $foreignKey) {
            if ($foreignKey->getLocalColumns() === [$column]) {
                return $foreignKey->getName();
            }
        }

        return null;
    }
}
