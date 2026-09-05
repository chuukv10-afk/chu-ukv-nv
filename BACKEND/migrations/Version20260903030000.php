<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260903030000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Référentiel signes vitaux + élargissement code acte_financier pour CONSULTATION';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE signe_vital (id INT AUTO_INCREMENT NOT NULL, code VARCHAR(15) NOT NULL, libelle VARCHAR(100) NOT NULL, unite VARCHAR(20) DEFAULT NULL, demande_au_triage TINYINT(1) DEFAULT 0 NOT NULL, obligatoire_au_triage TINYINT(1) DEFAULT 0 NOT NULL, ordre INT DEFAULT 0 NOT NULL, statut VARCHAR(20) NOT NULL, created_at DATETIME NOT NULL, UNIQUE INDEX UNIQ_SIGNE_VITAL_CODE (code), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE acte_financier CHANGE code code VARCHAR(20) NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE signe_vital');
        $this->addSql('ALTER TABLE acte_financier CHANGE code code VARCHAR(8) NOT NULL');
    }
}
