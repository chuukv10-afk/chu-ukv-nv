<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260906233000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Pharmacie P3–P5 : fournisseurs, réceptions, lots, mouvements, ventes.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE fournisseur (id INT AUTO_INCREMENT NOT NULL, code VARCHAR(15) NOT NULL, libelle VARCHAR(150) NOT NULL, telephone VARCHAR(20) DEFAULT NULL, adresse VARCHAR(200) DEFAULT NULL, statut VARCHAR(20) NOT NULL, created_at DATETIME NOT NULL, UNIQUE INDEX UNIQ_FOURNISSEUR_CODE (code), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');

        $this->addSql('CREATE TABLE reception (id INT AUTO_INCREMENT NOT NULL, fournisseur_id INT NOT NULL, numero VARCHAR(30) NOT NULL, date_reception DATE NOT NULL, reference_externe VARCHAR(80) DEFAULT NULL, statut VARCHAR(20) NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME DEFAULT NULL, created_by_id BINARY(16) DEFAULT NULL COMMENT \'(DC2Type:uuid)\', updated_by_id BINARY(16) DEFAULT NULL COMMENT \'(DC2Type:uuid)\', UNIQUE INDEX UNIQ_RECEPTION_NUMERO (numero), INDEX IDX_RECEPTION_FOURNISSEUR (fournisseur_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE reception ADD CONSTRAINT FK_RECEPTION_FOURNISSEUR FOREIGN KEY (fournisseur_id) REFERENCES fournisseur (id)');
        $this->addSql('ALTER TABLE reception ADD CONSTRAINT FK_RECEPTION_CREATED_BY FOREIGN KEY (created_by_id) REFERENCES personnel (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE reception ADD CONSTRAINT FK_RECEPTION_UPDATED_BY FOREIGN KEY (updated_by_id) REFERENCES personnel (id) ON DELETE SET NULL');

        $this->addSql('CREATE TABLE reception_ligne (id INT AUTO_INCREMENT NOT NULL, reception_id INT NOT NULL, medicament_id INT NOT NULL, numero_lot VARCHAR(40) NOT NULL, date_peremption DATE NOT NULL, quantite INT NOT NULL, prix_achat_unitaire NUMERIC(10, 4) NOT NULL, INDEX IDX_RECEPTION_LIGNE_RECEPTION (reception_id), INDEX IDX_RECEPTION_LIGNE_MEDICAMENT (medicament_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE reception_ligne ADD CONSTRAINT FK_RECEPTION_LIGNE_RECEPTION FOREIGN KEY (reception_id) REFERENCES reception (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE reception_ligne ADD CONSTRAINT FK_RECEPTION_LIGNE_MEDICAMENT FOREIGN KEY (medicament_id) REFERENCES medicament (id)');

        $this->addSql('CREATE TABLE lot (id INT AUTO_INCREMENT NOT NULL, medicament_id INT NOT NULL, reception_ligne_id INT DEFAULT NULL, numero_lot VARCHAR(40) NOT NULL, date_peremption DATE NOT NULL, quantite_restante INT NOT NULL, prix_achat_unitaire NUMERIC(10, 4) NOT NULL, statut VARCHAR(20) NOT NULL, UNIQUE INDEX UNIQ_LOT_MEDICAMENT_NUMERO (medicament_id, numero_lot), INDEX IDX_LOT_MEDICAMENT (medicament_id), INDEX IDX_LOT_RECEPTION_LIGNE (reception_ligne_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE lot ADD CONSTRAINT FK_LOT_MEDICAMENT FOREIGN KEY (medicament_id) REFERENCES medicament (id)');
        $this->addSql('ALTER TABLE lot ADD CONSTRAINT FK_LOT_RECEPTION_LIGNE FOREIGN KEY (reception_ligne_id) REFERENCES reception_ligne (id) ON DELETE SET NULL');

        $this->addSql('CREATE TABLE mouvement_stock (id INT AUTO_INCREMENT NOT NULL, lot_id INT NOT NULL, type VARCHAR(40) NOT NULL, sens VARCHAR(10) NOT NULL, quantite INT NOT NULL, document_type VARCHAR(30) NOT NULL, document_id INT NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME DEFAULT NULL, created_by_id BINARY(16) DEFAULT NULL COMMENT \'(DC2Type:uuid)\', updated_by_id BINARY(16) DEFAULT NULL COMMENT \'(DC2Type:uuid)\', INDEX IDX_MOUVEMENT_LOT (lot_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE mouvement_stock ADD CONSTRAINT FK_MOUVEMENT_LOT FOREIGN KEY (lot_id) REFERENCES lot (id)');
        $this->addSql('ALTER TABLE mouvement_stock ADD CONSTRAINT FK_MOUVEMENT_CREATED_BY FOREIGN KEY (created_by_id) REFERENCES personnel (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE mouvement_stock ADD CONSTRAINT FK_MOUVEMENT_UPDATED_BY FOREIGN KEY (updated_by_id) REFERENCES personnel (id) ON DELETE SET NULL');

        $this->addSql('CREATE TABLE vente (id INT AUTO_INCREMENT NOT NULL, patient_id BINARY(16) DEFAULT NULL COMMENT \'(DC2Type:uuid)\', annule_par_id BINARY(16) DEFAULT NULL COMMENT \'(DC2Type:uuid)\', numero VARCHAR(30) NOT NULL, date_vente DATETIME DEFAULT NULL, client_type VARCHAR(20) NOT NULL, client_nom VARCHAR(150) DEFAULT NULL, mode_paiement VARCHAR(20) NOT NULL, statut VARCHAR(20) NOT NULL, montant_total NUMERIC(12, 4) NOT NULL, annule_at DATETIME DEFAULT NULL, motif_annulation VARCHAR(255) DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME DEFAULT NULL, created_by_id BINARY(16) DEFAULT NULL COMMENT \'(DC2Type:uuid)\', updated_by_id BINARY(16) DEFAULT NULL COMMENT \'(DC2Type:uuid)\', UNIQUE INDEX UNIQ_VENTE_NUMERO (numero), INDEX IDX_VENTE_PATIENT (patient_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE vente ADD CONSTRAINT FK_VENTE_PATIENT FOREIGN KEY (patient_id) REFERENCES patient (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE vente ADD CONSTRAINT FK_VENTE_ANNULE_PAR FOREIGN KEY (annule_par_id) REFERENCES personnel (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE vente ADD CONSTRAINT FK_VENTE_CREATED_BY FOREIGN KEY (created_by_id) REFERENCES personnel (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE vente ADD CONSTRAINT FK_VENTE_UPDATED_BY FOREIGN KEY (updated_by_id) REFERENCES personnel (id) ON DELETE SET NULL');

        $this->addSql('CREATE TABLE vente_ligne (id INT AUTO_INCREMENT NOT NULL, vente_id INT NOT NULL, medicament_id INT NOT NULL, lot_id INT DEFAULT NULL, quantite INT NOT NULL, prix_unitaire NUMERIC(10, 4) NOT NULL, prix_total NUMERIC(12, 4) NOT NULL, INDEX IDX_VENTE_LIGNE_VENTE (vente_id), INDEX IDX_VENTE_LIGNE_MEDICAMENT (medicament_id), INDEX IDX_VENTE_LIGNE_LOT (lot_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE vente_ligne ADD CONSTRAINT FK_VENTE_LIGNE_VENTE FOREIGN KEY (vente_id) REFERENCES vente (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE vente_ligne ADD CONSTRAINT FK_VENTE_LIGNE_MEDICAMENT FOREIGN KEY (medicament_id) REFERENCES medicament (id)');
        $this->addSql('ALTER TABLE vente_ligne ADD CONSTRAINT FK_VENTE_LIGNE_LOT FOREIGN KEY (lot_id) REFERENCES lot (id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE vente_ligne DROP FOREIGN KEY FK_VENTE_LIGNE_VENTE');
        $this->addSql('ALTER TABLE vente_ligne DROP FOREIGN KEY FK_VENTE_LIGNE_MEDICAMENT');
        $this->addSql('ALTER TABLE vente_ligne DROP FOREIGN KEY FK_VENTE_LIGNE_LOT');
        $this->addSql('DROP TABLE vente_ligne');
        $this->addSql('ALTER TABLE vente DROP FOREIGN KEY FK_VENTE_PATIENT');
        $this->addSql('ALTER TABLE vente DROP FOREIGN KEY FK_VENTE_ANNULE_PAR');
        $this->addSql('ALTER TABLE vente DROP FOREIGN KEY FK_VENTE_CREATED_BY');
        $this->addSql('ALTER TABLE vente DROP FOREIGN KEY FK_VENTE_UPDATED_BY');
        $this->addSql('DROP TABLE vente');
        $this->addSql('ALTER TABLE mouvement_stock DROP FOREIGN KEY FK_MOUVEMENT_LOT');
        $this->addSql('ALTER TABLE mouvement_stock DROP FOREIGN KEY FK_MOUVEMENT_CREATED_BY');
        $this->addSql('ALTER TABLE mouvement_stock DROP FOREIGN KEY FK_MOUVEMENT_UPDATED_BY');
        $this->addSql('DROP TABLE mouvement_stock');
        $this->addSql('ALTER TABLE lot DROP FOREIGN KEY FK_LOT_MEDICAMENT');
        $this->addSql('ALTER TABLE lot DROP FOREIGN KEY FK_LOT_RECEPTION_LIGNE');
        $this->addSql('DROP TABLE lot');
        $this->addSql('ALTER TABLE reception_ligne DROP FOREIGN KEY FK_RECEPTION_LIGNE_RECEPTION');
        $this->addSql('ALTER TABLE reception_ligne DROP FOREIGN KEY FK_RECEPTION_LIGNE_MEDICAMENT');
        $this->addSql('DROP TABLE reception_ligne');
        $this->addSql('ALTER TABLE reception DROP FOREIGN KEY FK_RECEPTION_FOURNISSEUR');
        $this->addSql('ALTER TABLE reception DROP FOREIGN KEY FK_RECEPTION_CREATED_BY');
        $this->addSql('ALTER TABLE reception DROP FOREIGN KEY FK_RECEPTION_UPDATED_BY');
        $this->addSql('DROP TABLE reception');
        $this->addSql('DROP TABLE fournisseur');
    }
}
