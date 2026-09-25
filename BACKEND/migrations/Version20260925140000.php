<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260925140000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Patient : date de naissance optionnelle. Imagerie : source interne/externe, établissement et nom du médecin.';
    }

    public function up(Schema $schema): void
    {
        if ($schema->hasTable('patient') && $schema->getTable('patient')->hasColumn('date_naissance')) {
            $this->addSql('ALTER TABLE patient MODIFY date_naissance DATE DEFAULT NULL');
        }

        if ($schema->hasTable('etude_imagerie')) {
            $etude = $schema->getTable('etude_imagerie');
            if (!$etude->hasColumn('source')) {
                $this->addSql("ALTER TABLE etude_imagerie ADD source VARCHAR(20) NOT NULL DEFAULT 'INTERNE'");
            }
            if (!$etude->hasColumn('etablissement')) {
                $this->addSql('ALTER TABLE etude_imagerie ADD etablissement VARCHAR(255) DEFAULT NULL');
            }
            if (!$etude->hasColumn('demande_par_nom')) {
                $this->addSql('ALTER TABLE etude_imagerie ADD demande_par_nom VARCHAR(255) DEFAULT NULL');
            }
        }
    }

    public function down(Schema $schema): void
    {
        if ($schema->hasTable('etude_imagerie')) {
            $etude = $schema->getTable('etude_imagerie');
            if ($etude->hasColumn('demande_par_nom')) {
                $this->addSql('ALTER TABLE etude_imagerie DROP demande_par_nom');
            }
            if ($etude->hasColumn('etablissement')) {
                $this->addSql('ALTER TABLE etude_imagerie DROP etablissement');
            }
            if ($etude->hasColumn('source')) {
                $this->addSql('ALTER TABLE etude_imagerie DROP source');
            }
        }
    }
}
