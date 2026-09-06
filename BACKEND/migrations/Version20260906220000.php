<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260906220000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Pharmacie P2 : catalogue médicaments.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE medicament (id INT AUTO_INCREMENT NOT NULL, unite_id INT NOT NULL, famille_id INT NOT NULL, code VARCHAR(20) NOT NULL, libelle VARCHAR(150) NOT NULL, dci VARCHAR(150) DEFAULT NULL, forme VARCHAR(80) DEFAULT NULL, dosage VARCHAR(50) DEFAULT NULL, prix_vente NUMERIC(10, 4) NOT NULL, seuil_alerte INT DEFAULT 0 NOT NULL, statut VARCHAR(20) NOT NULL, created_at DATETIME NOT NULL, UNIQUE INDEX UNIQ_MEDICAMENT_CODE (code), INDEX IDX_MEDICAMENT_UNITE (unite_id), INDEX IDX_MEDICAMENT_FAMILLE (famille_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE medicament ADD CONSTRAINT FK_MEDICAMENT_UNITE FOREIGN KEY (unite_id) REFERENCES unite_medicament (id)');
        $this->addSql('ALTER TABLE medicament ADD CONSTRAINT FK_MEDICAMENT_FAMILLE FOREIGN KEY (famille_id) REFERENCES famille_medicament (id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE medicament DROP FOREIGN KEY FK_MEDICAMENT_UNITE');
        $this->addSql('ALTER TABLE medicament DROP FOREIGN KEY FK_MEDICAMENT_FAMILLE');
        $this->addSql('DROP TABLE medicament');
    }
}
