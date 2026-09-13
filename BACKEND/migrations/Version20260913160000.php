<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260913160000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Matricule personnel optionnel (vide / NU = sans matricule).';
    }

    public function up(Schema $schema): void
    {
        if (!$schema->hasTable('personnel')) {
            return;
        }
        $table = $schema->getTable('personnel');
        if (!$table->hasColumn('matricule')) {
            return;
        }

        $this->addSql("UPDATE personnel SET matricule = NULL WHERE matricule IS NULL OR TRIM(matricule) = '' OR UPPER(TRIM(matricule)) = 'NU'");
        $this->addSql('ALTER TABLE personnel CHANGE matricule matricule VARCHAR(20) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        if (!$schema->hasTable('personnel') || !$schema->getTable('personnel')->hasColumn('matricule')) {
            return;
        }

        $this->addSql("UPDATE personnel SET matricule = CONCAT('NU-', LEFT(REPLACE(id, '-', ''), 12)) WHERE matricule IS NULL OR TRIM(matricule) = ''");
        $this->addSql('ALTER TABLE personnel CHANGE matricule matricule VARCHAR(20) NOT NULL');
    }
}
