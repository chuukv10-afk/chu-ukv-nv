<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260909053000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Module Intendance : familles, types, locaux, parc et historique.';
    }

    public function up(Schema $schema): void
    {
        if (!$schema->hasTable('famille_bien')) {
            $this->addSql('CREATE TABLE famille_bien (id INT AUTO_INCREMENT NOT NULL, code VARCHAR(8) NOT NULL, libelle VARCHAR(100) NOT NULL, ordre INT DEFAULT 0 NOT NULL, statut VARCHAR(20) NOT NULL, seed TINYINT(1) DEFAULT 0 NOT NULL, created_at DATETIME NOT NULL, UNIQUE INDEX UNIQ_FAMILLE_BIEN_CODE (code), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        }

        if (!$schema->hasTable('type_bien')) {
            $this->addSql('CREATE TABLE type_bien (id INT AUTO_INCREMENT NOT NULL, code VARCHAR(20) NOT NULL, libelle VARCHAR(120) NOT NULL, ordre INT DEFAULT 0 NOT NULL, statut VARCHAR(20) NOT NULL, seed TINYINT(1) DEFAULT 0 NOT NULL, created_at DATETIME NOT NULL, famille_id INT NOT NULL, UNIQUE INDEX UNIQ_TYPE_BIEN_CODE (code), INDEX IDX_TYPE_BIEN_FAMILLE (famille_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
            $this->addSql('ALTER TABLE type_bien ADD CONSTRAINT FK_TYPE_BIEN_FAMILLE FOREIGN KEY (famille_id) REFERENCES famille_bien (id)');
        }

        if (!$schema->hasTable('local_intendance')) {
            $this->addSql('CREATE TABLE local_intendance (id INT AUTO_INCREMENT NOT NULL, code VARCHAR(20) NOT NULL, libelle VARCHAR(120) NOT NULL, ordre INT DEFAULT 0 NOT NULL, statut VARCHAR(20) NOT NULL, created_at DATETIME NOT NULL, service_id INT NOT NULL, UNIQUE INDEX UNIQ_LOCAL_INTENDANCE_SERVICE_CODE (service_id, code), INDEX IDX_LOCAL_INTENDANCE_SERVICE (service_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
            $this->addSql('ALTER TABLE local_intendance ADD CONSTRAINT FK_LOCAL_INTENDANCE_SERVICE FOREIGN KEY (service_id) REFERENCES service (id)');
        }

        if (!$schema->hasTable('bien_patrimonial')) {
            $this->addSql('CREATE TABLE bien_patrimonial (id INT AUTO_INCREMENT NOT NULL, code_inventaire VARCHAR(40) NOT NULL, precision_texte VARCHAR(150) DEFAULT NULL, marque VARCHAR(100) DEFAULT NULL, modele VARCHAR(100) DEFAULT NULL, numero_serie VARCHAR(80) DEFAULT NULL, complement_localisation VARCHAR(150) DEFAULT NULL, etat VARCHAR(8) NOT NULL, date_acquisition DATE DEFAULT NULL, observation VARCHAR(500) DEFAULT NULL, supprime_at DATETIME DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME DEFAULT NULL, type_id INT NOT NULL, famille_id INT NOT NULL, service_id INT NOT NULL, local_id INT DEFAULT NULL, created_by_id BINARY(16) DEFAULT NULL COMMENT \'(DC2Type:uuid)\', updated_by_id BINARY(16) DEFAULT NULL COMMENT \'(DC2Type:uuid)\', UNIQUE INDEX UNIQ_BIEN_CODE_INVENTAIRE (code_inventaire), INDEX IDX_BIEN_TYPE (type_id), INDEX IDX_BIEN_FAMILLE (famille_id), INDEX IDX_BIEN_SERVICE (service_id), INDEX IDX_BIEN_LOCAL (local_id), INDEX IDX_BIEN_CREATED_BY (created_by_id), INDEX IDX_BIEN_UPDATED_BY (updated_by_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
            $this->addSql('ALTER TABLE bien_patrimonial ADD CONSTRAINT FK_BIEN_TYPE FOREIGN KEY (type_id) REFERENCES type_bien (id)');
            $this->addSql('ALTER TABLE bien_patrimonial ADD CONSTRAINT FK_BIEN_FAMILLE FOREIGN KEY (famille_id) REFERENCES famille_bien (id)');
            $this->addSql('ALTER TABLE bien_patrimonial ADD CONSTRAINT FK_BIEN_SERVICE FOREIGN KEY (service_id) REFERENCES service (id)');
            $this->addSql('ALTER TABLE bien_patrimonial ADD CONSTRAINT FK_BIEN_LOCAL FOREIGN KEY (local_id) REFERENCES local_intendance (id) ON DELETE SET NULL');
            $this->addSql('ALTER TABLE bien_patrimonial ADD CONSTRAINT FK_BIEN_CREATED_BY FOREIGN KEY (created_by_id) REFERENCES personnel (id) ON DELETE SET NULL');
            $this->addSql('ALTER TABLE bien_patrimonial ADD CONSTRAINT FK_BIEN_UPDATED_BY FOREIGN KEY (updated_by_id) REFERENCES personnel (id) ON DELETE SET NULL');
        }

        if (!$schema->hasTable('historique_bien')) {
            $this->addSql('CREATE TABLE historique_bien (id INT AUTO_INCREMENT NOT NULL, type_evenement VARCHAR(30) NOT NULL, etat_avant VARCHAR(8) DEFAULT NULL, etat_apres VARCHAR(8) DEFAULT NULL, code_avant VARCHAR(40) DEFAULT NULL, code_apres VARCHAR(40) DEFAULT NULL, motif VARCHAR(500) DEFAULT NULL, created_at DATETIME NOT NULL, bien_id INT NOT NULL, service_avant_id INT DEFAULT NULL, service_apres_id INT DEFAULT NULL, local_avant_id INT DEFAULT NULL, local_apres_id INT DEFAULT NULL, created_by_id BINARY(16) DEFAULT NULL COMMENT \'(DC2Type:uuid)\', INDEX IDX_HIST_BIEN (bien_id), INDEX IDX_HIST_SVC_AVANT (service_avant_id), INDEX IDX_HIST_SVC_APRES (service_apres_id), INDEX IDX_HIST_LOC_AVANT (local_avant_id), INDEX IDX_HIST_LOC_APRES (local_apres_id), INDEX IDX_HIST_CREATED_BY (created_by_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
            $this->addSql('ALTER TABLE historique_bien ADD CONSTRAINT FK_HIST_BIEN FOREIGN KEY (bien_id) REFERENCES bien_patrimonial (id) ON DELETE CASCADE');
            $this->addSql('ALTER TABLE historique_bien ADD CONSTRAINT FK_HIST_SVC_AVANT FOREIGN KEY (service_avant_id) REFERENCES service (id) ON DELETE SET NULL');
            $this->addSql('ALTER TABLE historique_bien ADD CONSTRAINT FK_HIST_SVC_APRES FOREIGN KEY (service_apres_id) REFERENCES service (id) ON DELETE SET NULL');
            $this->addSql('ALTER TABLE historique_bien ADD CONSTRAINT FK_HIST_LOC_AVANT FOREIGN KEY (local_avant_id) REFERENCES local_intendance (id) ON DELETE SET NULL');
            $this->addSql('ALTER TABLE historique_bien ADD CONSTRAINT FK_HIST_LOC_APRES FOREIGN KEY (local_apres_id) REFERENCES local_intendance (id) ON DELETE SET NULL');
            $this->addSql('ALTER TABLE historique_bien ADD CONSTRAINT FK_HIST_CREATED_BY FOREIGN KEY (created_by_id) REFERENCES personnel (id) ON DELETE SET NULL');
        }
    }

    public function down(Schema $schema): void
    {
        if ($schema->hasTable('historique_bien')) {
            $this->addSql('ALTER TABLE historique_bien DROP FOREIGN KEY FK_HIST_BIEN');
            $this->addSql('ALTER TABLE historique_bien DROP FOREIGN KEY FK_HIST_SVC_AVANT');
            $this->addSql('ALTER TABLE historique_bien DROP FOREIGN KEY FK_HIST_SVC_APRES');
            $this->addSql('ALTER TABLE historique_bien DROP FOREIGN KEY FK_HIST_LOC_AVANT');
            $this->addSql('ALTER TABLE historique_bien DROP FOREIGN KEY FK_HIST_LOC_APRES');
            $this->addSql('ALTER TABLE historique_bien DROP FOREIGN KEY FK_HIST_CREATED_BY');
            $this->addSql('DROP TABLE historique_bien');
        }
        if ($schema->hasTable('bien_patrimonial')) {
            $this->addSql('ALTER TABLE bien_patrimonial DROP FOREIGN KEY FK_BIEN_TYPE');
            $this->addSql('ALTER TABLE bien_patrimonial DROP FOREIGN KEY FK_BIEN_FAMILLE');
            $this->addSql('ALTER TABLE bien_patrimonial DROP FOREIGN KEY FK_BIEN_SERVICE');
            $this->addSql('ALTER TABLE bien_patrimonial DROP FOREIGN KEY FK_BIEN_LOCAL');
            $this->addSql('ALTER TABLE bien_patrimonial DROP FOREIGN KEY FK_BIEN_CREATED_BY');
            $this->addSql('ALTER TABLE bien_patrimonial DROP FOREIGN KEY FK_BIEN_UPDATED_BY');
            $this->addSql('DROP TABLE bien_patrimonial');
        }
        if ($schema->hasTable('local_intendance')) {
            $this->addSql('ALTER TABLE local_intendance DROP FOREIGN KEY FK_LOCAL_INTENDANCE_SERVICE');
            $this->addSql('DROP TABLE local_intendance');
        }
        if ($schema->hasTable('type_bien')) {
            $this->addSql('ALTER TABLE type_bien DROP FOREIGN KEY FK_TYPE_BIEN_FAMILLE');
            $this->addSql('DROP TABLE type_bien');
        }
        if ($schema->hasTable('famille_bien')) {
            $this->addSql('DROP TABLE famille_bien');
        }
    }
}
