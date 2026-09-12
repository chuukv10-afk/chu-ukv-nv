<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260910233000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Filières UKV et lien sur les certificats d\'aptitude.';
    }

    public function up(Schema $schema): void
    {
        if (!$schema->hasTable('filiere')) {
            $this->addSql('CREATE TABLE filiere (
                id INT AUTO_INCREMENT NOT NULL,
                code VARCHAR(12) NOT NULL,
                libelle VARCHAR(150) NOT NULL,
                created_at DATETIME NOT NULL,
                UNIQUE INDEX UNIQ_FILIERE_CODE (code),
                PRIMARY KEY(id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        }

        if ($schema->hasTable('certificat_aptitude') && !$schema->getTable('certificat_aptitude')->hasColumn('filiere_id')) {
            $this->addSql('ALTER TABLE certificat_aptitude ADD filiere_id INT DEFAULT NULL');
            $this->addSql('ALTER TABLE certificat_aptitude ADD CONSTRAINT FK_CAP_FILIERE FOREIGN KEY (filiere_id) REFERENCES filiere (id) ON DELETE SET NULL');
            $this->addSql('CREATE INDEX IDX_CAP_FILIERE ON certificat_aptitude (filiere_id)');
        }
    }

    public function down(Schema $schema): void
    {
        if ($schema->hasTable('certificat_aptitude') && $schema->getTable('certificat_aptitude')->hasColumn('filiere_id')) {
            $this->addSql('ALTER TABLE certificat_aptitude DROP FOREIGN KEY FK_CAP_FILIERE');
            $this->addSql('DROP INDEX IDX_CAP_FILIERE ON certificat_aptitude');
            $this->addSql('ALTER TABLE certificat_aptitude DROP filiere_id');
        }

        if ($schema->hasTable('filiere')) {
            $this->addSql('DROP TABLE filiere');
        }
    }
}
