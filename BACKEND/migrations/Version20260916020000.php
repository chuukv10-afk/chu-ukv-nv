<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260916020000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Service habituel optionnel sur le référentiel des fonctions RH.';
    }

    public function up(Schema $schema): void
    {
        if (!$schema->hasTable('fonction') || !$schema->hasTable('service')) {
            return;
        }

        $table = $schema->getTable('fonction');
        if ($table->hasColumn('service_id')) {
            return;
        }

        $this->addSql('ALTER TABLE fonction ADD service_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE fonction ADD CONSTRAINT FK_FONCTION_SERVICE FOREIGN KEY (service_id) REFERENCES service (id) ON DELETE SET NULL');
        $this->addSql('CREATE INDEX IDX_FONCTION_SERVICE ON fonction (service_id)');
    }

    public function down(Schema $schema): void
    {
        if (!$schema->hasTable('fonction') || !$schema->getTable('fonction')->hasColumn('service_id')) {
            return;
        }

        $this->addSql('ALTER TABLE fonction DROP FOREIGN KEY FK_FONCTION_SERVICE');
        $this->addSql('DROP INDEX IDX_FONCTION_SERVICE ON fonction');
        $this->addSql('ALTER TABLE fonction DROP service_id');
    }
}
