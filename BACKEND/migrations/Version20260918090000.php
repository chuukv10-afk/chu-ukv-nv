<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260918090000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Organisations partenaires (type d\'institution), rattachement des filières, suivi d\'impression des certificats d\'aptitude.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE organisation_partenaire (
            id INT AUTO_INCREMENT NOT NULL,
            code VARCHAR(15) NOT NULL,
            libelle VARCHAR(150) NOT NULL,
            type_institution VARCHAR(30) NOT NULL,
            statut VARCHAR(20) NOT NULL,
            created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            UNIQUE INDEX UNIQ_ORG_PARTENAIRE_CODE (code),
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');

        $this->addSql("INSERT INTO organisation_partenaire (code, libelle, type_institution, statut, created_at)
            VALUES ('UKV', 'Université Kongo Vivi', 'UNIVERSITE', 'ACTIF', '2026-09-18 00:00:00')");

        $this->addSql('ALTER TABLE filiere ADD organisation_id INT DEFAULT NULL');
        $this->addSql('CREATE INDEX IDX_FILIERE_ORGANISATION ON filiere (organisation_id)');
        $this->addSql('ALTER TABLE filiere ADD CONSTRAINT FK_FILIERE_ORGANISATION FOREIGN KEY (organisation_id) REFERENCES organisation_partenaire (id) ON DELETE RESTRICT');
        $this->addSql("UPDATE filiere SET organisation_id = (SELECT id FROM organisation_partenaire WHERE code = 'UKV')");

        $this->addSql('ALTER TABLE patient ADD organisation_id INT DEFAULT NULL');
        $this->addSql('CREATE INDEX IDX_PATIENT_ORGANISATION ON patient (organisation_id)');
        $this->addSql('ALTER TABLE patient ADD CONSTRAINT FK_PATIENT_ORGANISATION FOREIGN KEY (organisation_id) REFERENCES organisation_partenaire (id) ON DELETE SET NULL');
        $this->addSql('UPDATE patient p INNER JOIN filiere f ON p.filiere_id = f.id SET p.organisation_id = f.organisation_id');

        $this->addSql('ALTER TABLE certificat_aptitude ADD imprime TINYINT(1) NOT NULL DEFAULT 0');
        $this->addSql('ALTER TABLE certificat_aptitude ADD imprime_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\'');
        $this->addSql('ALTER TABLE certificat_aptitude ADD imprime_par_id INT DEFAULT NULL');
        $this->addSql('CREATE INDEX IDX_CAP_IMPRIME ON certificat_aptitude (imprime)');
        $this->addSql('CREATE INDEX IDX_CAP_IMPRIME_PAR ON certificat_aptitude (imprime_par_id)');
        $this->addSql('ALTER TABLE certificat_aptitude ADD CONSTRAINT FK_CAP_IMPRIME_PAR FOREIGN KEY (imprime_par_id) REFERENCES personnel (id) ON DELETE SET NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE certificat_aptitude DROP FOREIGN KEY FK_CAP_IMPRIME_PAR');
        $this->addSql('DROP INDEX IDX_CAP_IMPRIME ON certificat_aptitude');
        $this->addSql('DROP INDEX IDX_CAP_IMPRIME_PAR ON certificat_aptitude');
        $this->addSql('ALTER TABLE certificat_aptitude DROP imprime, DROP imprime_at, DROP imprime_par_id');

        $this->addSql('ALTER TABLE patient DROP FOREIGN KEY FK_PATIENT_ORGANISATION');
        $this->addSql('DROP INDEX IDX_PATIENT_ORGANISATION ON patient');
        $this->addSql('ALTER TABLE patient DROP organisation_id');

        $this->addSql('ALTER TABLE filiere DROP FOREIGN KEY FK_FILIERE_ORGANISATION');
        $this->addSql('DROP INDEX IDX_FILIERE_ORGANISATION ON filiere');
        $this->addSql('ALTER TABLE filiere DROP organisation_id');

        $this->addSql('DROP TABLE organisation_partenaire');
    }
}
