<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260916010000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Référentiel des fonctions RH et lien sur le personnel.';
    }

    public function up(Schema $schema): void
    {
        if (!$schema->hasTable('fonction')) {
            $this->addSql('CREATE TABLE fonction (
                id INT AUTO_INCREMENT NOT NULL,
                code VARCHAR(12) NOT NULL,
                libelle VARCHAR(150) NOT NULL,
                created_at DATETIME NOT NULL,
                UNIQUE INDEX UNIQ_FONCTION_CODE (code),
                PRIMARY KEY(id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        }

        if ($schema->hasTable('personnel') && !$schema->getTable('personnel')->hasColumn('fonction_id')) {
            $this->addSql('ALTER TABLE personnel ADD fonction_id INT DEFAULT NULL');
            $this->addSql('ALTER TABLE personnel ADD CONSTRAINT FK_PERSONNEL_FONCTION FOREIGN KEY (fonction_id) REFERENCES fonction (id) ON DELETE SET NULL');
            $this->addSql('CREATE INDEX IDX_PERSONNEL_FONCTION ON personnel (fonction_id)');
        }
    }

    public function down(Schema $schema): void
    {
        if ($schema->hasTable('personnel') && $schema->getTable('personnel')->hasColumn('fonction_id')) {
            $this->addSql('ALTER TABLE personnel DROP FOREIGN KEY FK_PERSONNEL_FONCTION');
            $this->addSql('DROP INDEX IDX_PERSONNEL_FONCTION ON personnel');
            $this->addSql('ALTER TABLE personnel DROP fonction_id');
        }

        if ($schema->hasTable('fonction')) {
            $this->addSql('DROP TABLE fonction');
        }
    }
}
