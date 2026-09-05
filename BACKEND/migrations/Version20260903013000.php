<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260903013000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Élargit maladie.libelle/chapitre et ajoute une contrainte unique sur code_cim10';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE maladie CHANGE libelle libelle VARCHAR(255) NOT NULL');
        $this->addSql('ALTER TABLE maladie CHANGE chapitre chapitre VARCHAR(20) DEFAULT NULL');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_MALADIE_CODE_CIM10 ON maladie (code_cim10)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX UNIQ_MALADIE_CODE_CIM10 ON maladie');
        $this->addSql('ALTER TABLE maladie CHANGE libelle libelle VARCHAR(100) NOT NULL');
        $this->addSql('ALTER TABLE maladie CHANGE chapitre chapitre VARCHAR(10) DEFAULT NULL');
    }
}
