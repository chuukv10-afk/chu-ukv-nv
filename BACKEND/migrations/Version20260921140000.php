<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260921140000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Taux de marge unique par réception de médicaments.';
    }

    public function up(Schema $schema): void
    {
        if (!$schema->hasTable('reception')) {
            return;
        }
        $table = $schema->getTable('reception');
        if (!$table->hasColumn('taux_marge')) {
            $this->addSql('ALTER TABLE reception ADD taux_marge NUMERIC(8, 2) DEFAULT NULL');
        }
    }

    public function down(Schema $schema): void
    {
        if (!$schema->hasTable('reception')) {
            return;
        }
        $table = $schema->getTable('reception');
        if ($table->hasColumn('taux_marge')) {
            $this->addSql('ALTER TABLE reception DROP taux_marge');
        }
    }
}
