<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260830134400 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Élargit role.code à 20 caractères et corrige PERSONNE tronqué en PERSONNEL.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE role CHANGE code code VARCHAR(20) NOT NULL');
        $this->addSql("UPDATE role SET code = 'PERSONNEL' WHERE code = 'PERSONNE'");
    }

    public function down(Schema $schema): void
    {
        $this->addSql("UPDATE role SET code = 'PERSONNE' WHERE code = 'PERSONNEL'");
        $this->addSql('ALTER TABLE role CHANGE code code VARCHAR(8) NOT NULL');
    }
}
