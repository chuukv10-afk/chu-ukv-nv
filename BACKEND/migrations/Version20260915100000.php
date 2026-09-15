<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260915100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Pharmacie : campagnes d\'inventaire (lots figés, marquage compté, clôture).';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE inventaire_pharmacie (id INT AUTO_INCREMENT NOT NULL, numero VARCHAR(30) NOT NULL, libelle VARCHAR(150) NOT NULL, notes VARCHAR(255) DEFAULT NULL, statut VARCHAR(20) NOT NULL, date_debut DATE NOT NULL COMMENT \'(DC2Type:date_immutable)\', date_cloture DATE DEFAULT NULL COMMENT \'(DC2Type:date_immutable)\', lignes_count INT NOT NULL, lignes_comptees INT NOT NULL, produits_count INT NOT NULL, produits_comptes INT NOT NULL, cloture_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', updated_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', cloture_par_id BINARY(16) DEFAULT NULL COMMENT \'(DC2Type:uuid)\', created_by_id BINARY(16) DEFAULT NULL COMMENT \'(DC2Type:uuid)\', updated_by_id BINARY(16) DEFAULT NULL COMMENT \'(DC2Type:uuid)\', UNIQUE INDEX UNIQ_INV_PHARMA_NUMERO (numero), INDEX IDX_INV_PHARMA_STATUT (statut), INDEX IDX_INV_PHARMA_CLOTURE_PAR (cloture_par_id), INDEX IDX_INV_PHARMA_CREATED_BY (created_by_id), INDEX IDX_INV_PHARMA_UPDATED_BY (updated_by_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE inventaire_pharmacie_ligne (id INT AUTO_INCREMENT NOT NULL, numero_lot VARCHAR(40) NOT NULL, date_peremption DATE NOT NULL COMMENT \'(DC2Type:date_immutable)\', quantite_systeme INT NOT NULL, quantite_comptee INT DEFAULT NULL, compte TINYINT(1) NOT NULL, compte_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', inventaire_id INT NOT NULL, lot_id INT NOT NULL, medicament_id INT NOT NULL, compte_par_id BINARY(16) DEFAULT NULL COMMENT \'(DC2Type:uuid)\', mouvement_id INT DEFAULT NULL, UNIQUE INDEX UNIQ_INV_PHARMA_LIGNE_LOT (inventaire_id, lot_id), INDEX IDX_INV_PHARMA_LIGNE_INV (inventaire_id), INDEX IDX_INV_PHARMA_LIGNE_LOT (lot_id), INDEX IDX_INV_PHARMA_LIGNE_MED (medicament_id), INDEX IDX_INV_PHARMA_LIGNE_PAR (compte_par_id), INDEX IDX_INV_PHARMA_LIGNE_MVT (mouvement_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');

        $this->addSql('ALTER TABLE inventaire_pharmacie ADD CONSTRAINT FK_INV_PHARMA_CLOTURE_PAR FOREIGN KEY (cloture_par_id) REFERENCES personnel (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE inventaire_pharmacie ADD CONSTRAINT FK_INV_PHARMA_CREATED_BY FOREIGN KEY (created_by_id) REFERENCES personnel (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE inventaire_pharmacie ADD CONSTRAINT FK_INV_PHARMA_UPDATED_BY FOREIGN KEY (updated_by_id) REFERENCES personnel (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE inventaire_pharmacie_ligne ADD CONSTRAINT FK_INV_PHARMA_LIGNE_INV FOREIGN KEY (inventaire_id) REFERENCES inventaire_pharmacie (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE inventaire_pharmacie_ligne ADD CONSTRAINT FK_INV_PHARMA_LIGNE_LOT FOREIGN KEY (lot_id) REFERENCES lot (id)');
        $this->addSql('ALTER TABLE inventaire_pharmacie_ligne ADD CONSTRAINT FK_INV_PHARMA_LIGNE_MED FOREIGN KEY (medicament_id) REFERENCES medicament (id)');
        $this->addSql('ALTER TABLE inventaire_pharmacie_ligne ADD CONSTRAINT FK_INV_PHARMA_LIGNE_PAR FOREIGN KEY (compte_par_id) REFERENCES personnel (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE inventaire_pharmacie_ligne ADD CONSTRAINT FK_INV_PHARMA_LIGNE_MVT FOREIGN KEY (mouvement_id) REFERENCES mouvement_stock (id) ON DELETE SET NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE inventaire_pharmacie_ligne DROP FOREIGN KEY FK_INV_PHARMA_LIGNE_INV');
        $this->addSql('ALTER TABLE inventaire_pharmacie_ligne DROP FOREIGN KEY FK_INV_PHARMA_LIGNE_LOT');
        $this->addSql('ALTER TABLE inventaire_pharmacie_ligne DROP FOREIGN KEY FK_INV_PHARMA_LIGNE_MED');
        $this->addSql('ALTER TABLE inventaire_pharmacie_ligne DROP FOREIGN KEY FK_INV_PHARMA_LIGNE_PAR');
        $this->addSql('ALTER TABLE inventaire_pharmacie_ligne DROP FOREIGN KEY FK_INV_PHARMA_LIGNE_MVT');
        $this->addSql('ALTER TABLE inventaire_pharmacie DROP FOREIGN KEY FK_INV_PHARMA_CLOTURE_PAR');
        $this->addSql('ALTER TABLE inventaire_pharmacie DROP FOREIGN KEY FK_INV_PHARMA_CREATED_BY');
        $this->addSql('ALTER TABLE inventaire_pharmacie DROP FOREIGN KEY FK_INV_PHARMA_UPDATED_BY');
        $this->addSql('DROP TABLE inventaire_pharmacie_ligne');
        $this->addSql('DROP TABLE inventaire_pharmacie');
    }
}
