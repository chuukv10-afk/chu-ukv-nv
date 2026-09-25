<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260925120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Imagerie : but et médecin demandeur sur l\'étude et la demande d\'examen.';
    }

    public function up(Schema $schema): void
    {
        if ($schema->hasTable('etude_imagerie')) {
            $etude = $schema->getTable('etude_imagerie');
            if (!$etude->hasColumn('but')) {
                $this->addSql('ALTER TABLE etude_imagerie ADD but VARCHAR(255) DEFAULT NULL');
            }
            if (!$etude->hasColumn('demande_par_id')) {
                $this->addSql('ALTER TABLE etude_imagerie ADD demande_par_id BINARY(16) DEFAULT NULL COMMENT \'(DC2Type:uuid)\'');
                $this->addSql('CREATE INDEX IDX_ETUDE_IMG_DEMANDE_PAR ON etude_imagerie (demande_par_id)');
                $this->addSql('ALTER TABLE etude_imagerie ADD CONSTRAINT FK_ETUDE_IMG_DEMANDE_PAR FOREIGN KEY (demande_par_id) REFERENCES personnel (id) ON DELETE SET NULL');
            }
        }

        if ($schema->hasTable('demande_examen')) {
            $demande = $schema->getTable('demande_examen');
            if (!$demande->hasColumn('but')) {
                $this->addSql('ALTER TABLE demande_examen ADD but VARCHAR(255) DEFAULT NULL');
            }
        }
    }

    public function down(Schema $schema): void
    {
        if ($schema->hasTable('etude_imagerie')) {
            $etude = $schema->getTable('etude_imagerie');
            if ($etude->hasColumn('demande_par_id')) {
                $this->addSql('ALTER TABLE etude_imagerie DROP FOREIGN KEY FK_ETUDE_IMG_DEMANDE_PAR');
                $this->addSql('ALTER TABLE etude_imagerie DROP demande_par_id');
            }
            if ($etude->hasColumn('but')) {
                $this->addSql('ALTER TABLE etude_imagerie DROP but');
            }
        }
        if ($schema->hasTable('demande_examen') && $schema->getTable('demande_examen')->hasColumn('but')) {
            $this->addSql('ALTER TABLE demande_examen DROP but');
        }
    }
}
