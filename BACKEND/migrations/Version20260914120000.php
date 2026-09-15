<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260914120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Facturation : structures partenaires, catégories patient/visite, colonnes A0–C de la grille.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE structure (id INT AUTO_INCREMENT NOT NULL, code VARCHAR(15) NOT NULL, libelle VARCHAR(150) NOT NULL, type VARCHAR(20) NOT NULL, telephone VARCHAR(20) DEFAULT NULL, adresse VARCHAR(200) DEFAULT NULL, statut VARCHAR(20) NOT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', UNIQUE INDEX UNIQ_STRUCTURE_CODE (code), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');

        $this->addSql('ALTER TABLE patient ADD categorie_tarifaire VARCHAR(3) DEFAULT NULL, ADD numero_affiliation VARCHAR(50) DEFAULT NULL, ADD structure_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE patient ADD CONSTRAINT FK_PATIENT_STRUCTURE FOREIGN KEY (structure_id) REFERENCES structure (id) ON DELETE SET NULL');
        $this->addSql('CREATE INDEX IDX_PATIENT_STRUCTURE ON patient (structure_id)');
        $this->addSql('CREATE INDEX IDX_PATIENT_CATEGORIE ON patient (categorie_tarifaire)');

        $this->addSql('ALTER TABLE visite ADD categorie_tarifaire VARCHAR(3) DEFAULT NULL, ADD numero_affiliation VARCHAR(50) DEFAULT NULL, ADD structure_libelle VARCHAR(150) DEFAULT NULL, ADD structure_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE visite ADD CONSTRAINT FK_VISITE_STRUCTURE FOREIGN KEY (structure_id) REFERENCES structure (id) ON DELETE SET NULL');
        $this->addSql('CREATE INDEX IDX_VISITE_STRUCTURE ON visite (structure_id)');

        $this->addSql('ALTER TABLE acte_financier CHANGE code code VARCHAR(40) NOT NULL, CHANGE libelle libelle VARCHAR(180) NOT NULL, CHANGE tarif tarif NUMERIC(12, 2) NOT NULL');
        $this->addSql('ALTER TABLE acte_financier ADD service_grille VARCHAR(40) DEFAULT NULL, ADD sous_categorie VARCHAR(80) DEFAULT NULL, ADD tarif_a0 NUMERIC(12, 2) DEFAULT \'0.00\' NOT NULL, ADD tarif_a1 NUMERIC(12, 2) DEFAULT \'0.00\' NOT NULL, ADD tarif_b NUMERIC(12, 2) DEFAULT \'0.00\' NOT NULL, ADD tarif_c NUMERIC(12, 2) DEFAULT \'0.00\' NOT NULL');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_ACTE_FIN_CODE ON acte_financier (code)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_ACTE_FIN_SERVICE_LIB ON acte_financier (service_grille, libelle)');
        $this->addSql('CREATE INDEX IDX_ACTE_FIN_SERVICE ON acte_financier (service_grille)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE patient DROP FOREIGN KEY FK_PATIENT_STRUCTURE');
        $this->addSql('ALTER TABLE visite DROP FOREIGN KEY FK_VISITE_STRUCTURE');
        $this->addSql('DROP INDEX IDX_PATIENT_STRUCTURE ON patient');
        $this->addSql('DROP INDEX IDX_PATIENT_CATEGORIE ON patient');
        $this->addSql('ALTER TABLE patient DROP categorie_tarifaire, DROP numero_affiliation, DROP structure_id');
        $this->addSql('DROP INDEX IDX_VISITE_STRUCTURE ON visite');
        $this->addSql('ALTER TABLE visite DROP categorie_tarifaire, DROP numero_affiliation, DROP structure_libelle, DROP structure_id');
        $this->addSql('DROP INDEX UNIQ_ACTE_FIN_CODE ON acte_financier');
        $this->addSql('DROP INDEX UNIQ_ACTE_FIN_SERVICE_LIB ON acte_financier');
        $this->addSql('DROP INDEX IDX_ACTE_FIN_SERVICE ON acte_financier');
        $this->addSql('ALTER TABLE acte_financier DROP service_grille, DROP sous_categorie, DROP tarif_a0, DROP tarif_a1, DROP tarif_b, DROP tarif_c, CHANGE code code VARCHAR(20) NOT NULL, CHANGE libelle libelle VARCHAR(50) NOT NULL, CHANGE tarif tarif NUMERIC(10, 4) NOT NULL');
        $this->addSql('DROP TABLE structure');
    }
}
