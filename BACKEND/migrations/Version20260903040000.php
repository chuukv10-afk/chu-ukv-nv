<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260903040000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Triage visite et mesures de signes vitaux';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE triage (id INT AUTO_INCREMENT NOT NULL, type_entree VARCHAR(20) NOT NULL, priorite INT DEFAULT NULL, motif VARCHAR(255) NOT NULL, triaged_at DATETIME NOT NULL, visite_id INT NOT NULL, triaged_by_id BINARY(16) DEFAULT NULL, UNIQUE INDEX UNIQ_TRIAGE_VISITE (visite_id), INDEX IDX_TRIAGE_PERSONNEL (triaged_by_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE triage_mesure (id INT AUTO_INCREMENT NOT NULL, valeur VARCHAR(50) NOT NULL, triage_id INT NOT NULL, signe_vital_id INT NOT NULL, INDEX IDX_TRIAGE_MESURE_TRIAGE (triage_id), INDEX IDX_TRIAGE_MESURE_SIGNE (signe_vital_id), UNIQUE INDEX UNIQ_TRIAGE_MESURE (triage_id, signe_vital_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE triage ADD CONSTRAINT FK_TRIAGE_VISITE FOREIGN KEY (visite_id) REFERENCES visite (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE triage ADD CONSTRAINT FK_TRIAGE_PERSONNEL FOREIGN KEY (triaged_by_id) REFERENCES personnel (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE triage_mesure ADD CONSTRAINT FK_TRIAGE_MESURE_TRIAGE FOREIGN KEY (triage_id) REFERENCES triage (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE triage_mesure ADD CONSTRAINT FK_TRIAGE_MESURE_SIGNE FOREIGN KEY (signe_vital_id) REFERENCES signe_vital (id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE triage_mesure DROP FOREIGN KEY FK_TRIAGE_MESURE_TRIAGE');
        $this->addSql('ALTER TABLE triage_mesure DROP FOREIGN KEY FK_TRIAGE_MESURE_SIGNE');
        $this->addSql('ALTER TABLE triage DROP FOREIGN KEY FK_TRIAGE_VISITE');
        $this->addSql('ALTER TABLE triage DROP FOREIGN KEY FK_TRIAGE_PERSONNEL');
        $this->addSql('DROP TABLE triage_mesure');
        $this->addSql('DROP TABLE triage');
    }
}
