<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260902235000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajoute le champ avatar_filename sur personnel pour la photo de profil.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE personnel ADD avatar_filename VARCHAR(255) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE personnel DROP avatar_filename');
    }
}
