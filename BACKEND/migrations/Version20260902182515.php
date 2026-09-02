<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260902182515 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajoute le périmètre obligatoire sur la table role.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE role ADD perimetre VARCHAR(20) DEFAULT 'GLOBAL' NOT NULL");
        $this->addSql("UPDATE role SET perimetre = 'GLOBAL' WHERE code = 'ADMIN'");
        $this->addSql("UPDATE role SET perimetre = 'SERVICE' WHERE code = 'PERSONNEL'");
        $this->addSql('ALTER TABLE role ALTER perimetre DROP DEFAULT');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE role DROP perimetre');
    }
}
