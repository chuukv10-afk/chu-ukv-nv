<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260903020000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Supprime visite.type_visite (statut unique pour le cycle de visite)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE visite DROP type_visite');
    }

    public function down(Schema $schema): void
    {
        $this->addSql("ALTER TABLE visite ADD type_visite VARCHAR(20) NOT NULL DEFAULT 'EXTERNE'");
    }
}
