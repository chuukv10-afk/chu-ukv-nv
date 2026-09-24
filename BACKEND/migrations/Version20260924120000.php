<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260924120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Tables facture et facture_ligne pour le module de facturation.';
    }

    public function up(Schema $schema): void
    {
        if (!$schema->hasTable('facture')) {
            $this->addSql('CREATE TABLE facture (
                id INT AUTO_INCREMENT NOT NULL,
                patient_id BINARY(16) NOT NULL COMMENT \'(DC2Type:uuid)\',
                structure_id INT DEFAULT NULL,
                created_by_id INT DEFAULT NULL,
                updated_by_id INT DEFAULT NULL,
                numero VARCHAR(40) NOT NULL,
                date_facture DATE NOT NULL,
                categorie_tarifaire VARCHAR(8) NOT NULL,
                numero_affiliation VARCHAR(80) DEFAULT NULL,
                statut VARCHAR(16) NOT NULL,
                montant_total NUMERIC(12, 2) NOT NULL,
                notes LONGTEXT DEFAULT NULL,
                created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\',
                updated_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\',
                UNIQUE INDEX UNIQ_FACTURE_NUMERO (numero),
                INDEX IDX_FACTURE_PATIENT (patient_id),
                INDEX IDX_FACTURE_STRUCTURE (structure_id),
                INDEX IDX_FACTURE_STATUT (statut),
                INDEX IDX_FACTURE_DATE (date_facture),
                INDEX IDX_FACTURE_CREATED_BY (created_by_id),
                INDEX IDX_FACTURE_UPDATED_BY (updated_by_id),
                PRIMARY KEY(id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
            $this->addSql('ALTER TABLE facture ADD CONSTRAINT FK_FACTURE_PATIENT FOREIGN KEY (patient_id) REFERENCES patient (id) ON DELETE RESTRICT');
            $this->addSql('ALTER TABLE facture ADD CONSTRAINT FK_FACTURE_STRUCTURE FOREIGN KEY (structure_id) REFERENCES structure (id) ON DELETE SET NULL');
            $this->addSql('ALTER TABLE facture ADD CONSTRAINT FK_FACTURE_CREATED_BY FOREIGN KEY (created_by_id) REFERENCES personnel (id) ON DELETE SET NULL');
            $this->addSql('ALTER TABLE facture ADD CONSTRAINT FK_FACTURE_UPDATED_BY FOREIGN KEY (updated_by_id) REFERENCES personnel (id) ON DELETE SET NULL');
        }

        if (!$schema->hasTable('facture_ligne')) {
            $this->addSql('CREATE TABLE facture_ligne (
                id INT AUTO_INCREMENT NOT NULL,
                facture_id INT NOT NULL,
                acte_id INT DEFAULT NULL,
                code_acte VARCHAR(40) NOT NULL,
                libelle VARCHAR(255) NOT NULL,
                service_grille VARCHAR(80) DEFAULT NULL,
                quantite INT NOT NULL,
                tarif_unitaire NUMERIC(12, 2) NOT NULL,
                tarif_total NUMERIC(12, 2) NOT NULL,
                INDEX IDX_FACTURE_LIGNE_FACTURE (facture_id),
                INDEX IDX_FACTURE_LIGNE_ACTE (acte_id),
                PRIMARY KEY(id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
            $this->addSql('ALTER TABLE facture_ligne ADD CONSTRAINT FK_FACTURE_LIGNE_FACTURE FOREIGN KEY (facture_id) REFERENCES facture (id) ON DELETE CASCADE');
            $this->addSql('ALTER TABLE facture_ligne ADD CONSTRAINT FK_FACTURE_LIGNE_ACTE FOREIGN KEY (acte_id) REFERENCES acte_financier (id) ON DELETE SET NULL');
        }
    }

    public function down(Schema $schema): void
    {
        if ($schema->hasTable('facture_ligne')) {
            $this->addSql('ALTER TABLE facture_ligne DROP FOREIGN KEY FK_FACTURE_LIGNE_FACTURE');
            $this->addSql('ALTER TABLE facture_ligne DROP FOREIGN KEY FK_FACTURE_LIGNE_ACTE');
            $this->addSql('DROP TABLE facture_ligne');
        }
        if ($schema->hasTable('facture')) {
            $this->addSql('ALTER TABLE facture DROP FOREIGN KEY FK_FACTURE_PATIENT');
            $this->addSql('ALTER TABLE facture DROP FOREIGN KEY FK_FACTURE_STRUCTURE');
            $this->addSql('ALTER TABLE facture DROP FOREIGN KEY FK_FACTURE_CREATED_BY');
            $this->addSql('ALTER TABLE facture DROP FOREIGN KEY FK_FACTURE_UPDATED_BY');
            $this->addSql('DROP TABLE facture');
        }
    }
}
