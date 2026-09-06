<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260907020000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Pharmacie P6–P8 : visite hospitalisée, demandes service, motif mouvement.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE vente ADD visite_id INT DEFAULT NULL, ADD origine VARCHAR(20) DEFAULT 'COMPTOIR' NOT NULL");
        $this->addSql('ALTER TABLE vente ADD CONSTRAINT FK_VENTE_VISITE FOREIGN KEY (visite_id) REFERENCES visite (id) ON DELETE SET NULL');
        $this->addSql('CREATE INDEX IDX_VENTE_VISITE ON vente (visite_id)');

        $this->addSql('ALTER TABLE mouvement_stock ADD motif VARCHAR(255) DEFAULT NULL');

        $this->addSql('CREATE TABLE demande_service (id INT AUTO_INCREMENT NOT NULL, service_id INT NOT NULL, paye_par_id BINARY(16) DEFAULT NULL COMMENT \'(DC2Type:uuid)\', numero VARCHAR(30) NOT NULL, motif VARCHAR(255) DEFAULT NULL, statut VARCHAR(20) NOT NULL, statut_paiement VARCHAR(20) NOT NULL, montant_total NUMERIC(12, 4) NOT NULL, mode_paiement VARCHAR(20) DEFAULT NULL, paye_at DATETIME DEFAULT NULL, motif_refus VARCHAR(255) DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME DEFAULT NULL, created_by_id BINARY(16) DEFAULT NULL COMMENT \'(DC2Type:uuid)\', updated_by_id BINARY(16) DEFAULT NULL COMMENT \'(DC2Type:uuid)\', UNIQUE INDEX UNIQ_DEMANDE_SERVICE_NUMERO (numero), INDEX IDX_DEMANDE_SERVICE_SERVICE (service_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE demande_service ADD CONSTRAINT FK_DEMANDE_SERVICE_SERVICE FOREIGN KEY (service_id) REFERENCES service (id)');
        $this->addSql('ALTER TABLE demande_service ADD CONSTRAINT FK_DEMANDE_SERVICE_PAYE_PAR FOREIGN KEY (paye_par_id) REFERENCES personnel (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE demande_service ADD CONSTRAINT FK_DEMANDE_SERVICE_CREATED_BY FOREIGN KEY (created_by_id) REFERENCES personnel (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE demande_service ADD CONSTRAINT FK_DEMANDE_SERVICE_UPDATED_BY FOREIGN KEY (updated_by_id) REFERENCES personnel (id) ON DELETE SET NULL');

        $this->addSql('CREATE TABLE demande_service_ligne (id INT AUTO_INCREMENT NOT NULL, demande_id INT NOT NULL, medicament_id INT NOT NULL, lot_id INT DEFAULT NULL, quantite INT NOT NULL, prix_unitaire NUMERIC(10, 4) NOT NULL, prix_total NUMERIC(12, 4) NOT NULL, INDEX IDX_DEMANDE_LIGNE_DEMANDE (demande_id), INDEX IDX_DEMANDE_LIGNE_MEDICAMENT (medicament_id), INDEX IDX_DEMANDE_LIGNE_LOT (lot_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE demande_service_ligne ADD CONSTRAINT FK_DEMANDE_LIGNE_DEMANDE FOREIGN KEY (demande_id) REFERENCES demande_service (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE demande_service_ligne ADD CONSTRAINT FK_DEMANDE_LIGNE_MEDICAMENT FOREIGN KEY (medicament_id) REFERENCES medicament (id)');
        $this->addSql('ALTER TABLE demande_service_ligne ADD CONSTRAINT FK_DEMANDE_LIGNE_LOT FOREIGN KEY (lot_id) REFERENCES lot (id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE demande_service_ligne DROP FOREIGN KEY FK_DEMANDE_LIGNE_DEMANDE');
        $this->addSql('ALTER TABLE demande_service_ligne DROP FOREIGN KEY FK_DEMANDE_LIGNE_MEDICAMENT');
        $this->addSql('ALTER TABLE demande_service_ligne DROP FOREIGN KEY FK_DEMANDE_LIGNE_LOT');
        $this->addSql('DROP TABLE demande_service_ligne');
        $this->addSql('ALTER TABLE demande_service DROP FOREIGN KEY FK_DEMANDE_SERVICE_SERVICE');
        $this->addSql('ALTER TABLE demande_service DROP FOREIGN KEY FK_DEMANDE_SERVICE_PAYE_PAR');
        $this->addSql('ALTER TABLE demande_service DROP FOREIGN KEY FK_DEMANDE_SERVICE_CREATED_BY');
        $this->addSql('ALTER TABLE demande_service DROP FOREIGN KEY FK_DEMANDE_SERVICE_UPDATED_BY');
        $this->addSql('DROP TABLE demande_service');
        $this->addSql('ALTER TABLE mouvement_stock DROP motif');
        $this->addSql('ALTER TABLE vente DROP FOREIGN KEY FK_VENTE_VISITE');
        $this->addSql('DROP INDEX IDX_VENTE_VISITE ON vente');
        $this->addSql('ALTER TABLE vente DROP visite_id, DROP origine');
    }
}
