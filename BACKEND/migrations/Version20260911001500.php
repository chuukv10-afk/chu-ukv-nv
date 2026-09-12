<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260911001500 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajoute le champ signature_filename sur personnel.';
    }

    public function up(Schema $schema): void
    {
        if ($schema->hasTable('personnel') && !$schema->getTable('personnel')->hasColumn('signature_filename')) {
            $this->addSql('ALTER TABLE personnel ADD signature_filename VARCHAR(255) DEFAULT NULL');
        }
    }

    public function down(Schema $schema): void
    {
        if ($schema->hasTable('personnel') && $schema->getTable('personnel')->hasColumn('signature_filename')) {
            $this->addSql('ALTER TABLE personnel DROP signature_filename');
        }
    }
}
