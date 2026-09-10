<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260910073000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Module Aptitude physique : certificats d\'aptitude.';
    }

    public function up(Schema $schema): void
    {
        if ($schema->hasTable('certificat_aptitude')) {
            return;
        }

        $this->addSql('CREATE TABLE certificat_aptitude (
            id INT AUTO_INCREMENT NOT NULL,
            numero VARCHAR(40) DEFAULT NULL,
            annee INT NOT NULL,
            statut VARCHAR(20) NOT NULL,
            nom VARCHAR(50) NOT NULL,
            post_nom VARCHAR(50) NOT NULL,
            prenom VARCHAR(50) DEFAULT NULL,
            sexe VARCHAR(1) NOT NULL,
            etat_civil VARCHAR(50) DEFAULT NULL,
            date_naissance DATE DEFAULT NULL,
            lieu_naissance VARCHAR(80) DEFAULT NULL,
            adresse VARCHAR(150) DEFAULT NULL,
            motif VARCHAR(20) NOT NULL,
            motif_autre VARCHAR(150) DEFAULT NULL,
            poids_kg NUMERIC(6, 2) DEFAULT NULL,
            taille_cm NUMERIC(6, 2) DEFAULT NULL,
            perimetre_thoracique_cm NUMERIC(6, 2) DEFAULT NULL,
            p1 INT DEFAULT NULL,
            p2 INT DEFAULT NULL,
            p3 INT DEFAULT NULL,
            imc NUMERIC(6, 2) DEFAULT NULL,
            imc_classe VARCHAR(20) DEFAULT NULL,
            pignet NUMERIC(8, 2) DEFAULT NULL,
            pignet_robustesse VARCHAR(20) DEFAULT NULL,
            ruffier NUMERIC(8, 2) DEFAULT NULL,
            dickson NUMERIC(8, 2) DEFAULT NULL,
            ruffier_classe VARCHAR(20) DEFAULT NULL,
            verdict_propose VARCHAR(20) DEFAULT NULL,
            verdict VARCHAR(20) DEFAULT NULL,
            signe_at DATETIME DEFAULT NULL,
            valide_jusqua DATETIME DEFAULT NULL,
            annule_at DATETIME DEFAULT NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME DEFAULT NULL,
            service_id INT NOT NULL,
            patient_id BINARY(16) DEFAULT NULL COMMENT \'(DC2Type:uuid)\',
            signe_par_id BINARY(16) DEFAULT NULL COMMENT \'(DC2Type:uuid)\',
            created_by_id BINARY(16) DEFAULT NULL COMMENT \'(DC2Type:uuid)\',
            updated_by_id BINARY(16) DEFAULT NULL COMMENT \'(DC2Type:uuid)\',
            UNIQUE INDEX UNIQ_CAP_NUMERO (numero),
            INDEX IDX_CAP_SERVICE (service_id),
            INDEX IDX_CAP_PATIENT (patient_id),
            INDEX IDX_CAP_SIGNE_PAR (signe_par_id),
            INDEX IDX_CAP_CREATED_BY (created_by_id),
            INDEX IDX_CAP_UPDATED_BY (updated_by_id),
            INDEX IDX_CAP_STATUT (statut),
            INDEX IDX_CAP_ANNEE (annee),
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE certificat_aptitude ADD CONSTRAINT FK_CAP_SERVICE FOREIGN KEY (service_id) REFERENCES service (id)');
        $this->addSql('ALTER TABLE certificat_aptitude ADD CONSTRAINT FK_CAP_PATIENT FOREIGN KEY (patient_id) REFERENCES patient (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE certificat_aptitude ADD CONSTRAINT FK_CAP_SIGNE_PAR FOREIGN KEY (signe_par_id) REFERENCES personnel (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE certificat_aptitude ADD CONSTRAINT FK_CAP_CREATED_BY FOREIGN KEY (created_by_id) REFERENCES personnel (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE certificat_aptitude ADD CONSTRAINT FK_CAP_UPDATED_BY FOREIGN KEY (updated_by_id) REFERENCES personnel (id) ON DELETE SET NULL');
    }

    public function down(Schema $schema): void
    {
        if (!$schema->hasTable('certificat_aptitude')) {
            return;
        }

        $this->addSql('ALTER TABLE certificat_aptitude DROP FOREIGN KEY FK_CAP_SERVICE');
        $this->addSql('ALTER TABLE certificat_aptitude DROP FOREIGN KEY FK_CAP_PATIENT');
        $this->addSql('ALTER TABLE certificat_aptitude DROP FOREIGN KEY FK_CAP_SIGNE_PAR');
        $this->addSql('ALTER TABLE certificat_aptitude DROP FOREIGN KEY FK_CAP_CREATED_BY');
        $this->addSql('ALTER TABLE certificat_aptitude DROP FOREIGN KEY FK_CAP_UPDATED_BY');
        $this->addSql('DROP TABLE certificat_aptitude');
    }
}
