<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261111120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Réception : motif, date et auteur d’annulation.';
    }

    public function up(Schema $schema): void
    {
        if (!$schema->hasTable('reception')) {
            return;
        }

        $table = $schema->getTable('reception');
        if (!$table->hasColumn('annule_at')) {
            $this->addSql('ALTER TABLE reception ADD annule_at DATETIME DEFAULT NULL');
        }
        if (!$table->hasColumn('motif_annulation')) {
            $this->addSql('ALTER TABLE reception ADD motif_annulation VARCHAR(255) DEFAULT NULL');
        }
        if (!$table->hasColumn('annule_par_id')) {
            $this->addSql('ALTER TABLE reception ADD annule_par_id BINARY(16) DEFAULT NULL COMMENT \'(DC2Type:uuid)\'');
            $this->addSql('ALTER TABLE reception ADD CONSTRAINT FK_RECEPTION_ANNULE_PAR FOREIGN KEY (annule_par_id) REFERENCES personnel (id) ON DELETE SET NULL');
            $this->addSql('CREATE INDEX IDX_RECEPTION_ANNULE_PAR ON reception (annule_par_id)');
        }
    }

    public function down(Schema $schema): void
    {
        if (!$schema->hasTable('reception')) {
            return;
        }

        $table = $schema->getTable('reception');
        if ($table->hasColumn('annule_par_id')) {
            $this->addSql('ALTER TABLE reception DROP FOREIGN KEY FK_RECEPTION_ANNULE_PAR');
            $this->addSql('DROP INDEX IDX_RECEPTION_ANNULE_PAR ON reception');
            $this->addSql('ALTER TABLE reception DROP annule_par_id');
        }
        if ($table->hasColumn('motif_annulation')) {
            $this->addSql('ALTER TABLE reception DROP motif_annulation');
        }
        if ($table->hasColumn('annule_at')) {
            $this->addSql('ALTER TABLE reception DROP annule_at');
        }
    }
}
