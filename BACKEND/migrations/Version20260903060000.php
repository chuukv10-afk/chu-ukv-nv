<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260903060000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Consultation clinique enrichie (examen physique JSON, clôture) et mesures visite';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE consultation
            MODIFY motif LONGTEXT DEFAULT NULL,
            MODIFY histoire_maladie LONGTEXT DEFAULT NULL,
            MODIFY conduire_a_tenir LONGTEXT DEFAULT NULL,
            MODIFY consultation_observation LONGTEXT DEFAULT NULL');
        $this->addSql('ALTER TABLE consultation
            ADD physical_exam JSON DEFAULT NULL,
            ADD complement_anamnese JSON DEFAULT NULL,
            ADD needs_hospitalization TINYINT(1) DEFAULT NULL,
            ADD wants_appointment TINYINT(1) DEFAULT NULL,
            ADD next_appointment_at DATETIME DEFAULT NULL,
            ADD hospitalization_patient_opinion VARCHAR(20) DEFAULT NULL,
            ADD hospitalization_observation LONGTEXT DEFAULT NULL');
        $this->addSql('ALTER TABLE consultation ADD closed_by_id BINARY(16) DEFAULT NULL');
        $this->addSql('CREATE INDEX IDX_CONSULTATION_CLOSED_BY ON consultation (closed_by_id)');
        $this->addSql('ALTER TABLE consultation ADD CONSTRAINT FK_CONSULTATION_CLOSED_BY FOREIGN KEY (closed_by_id) REFERENCES personnel (id) ON DELETE SET NULL');

        $this->addSql('CREATE TABLE visite_mesure (
            id INT AUTO_INCREMENT NOT NULL,
            valeur VARCHAR(50) NOT NULL,
            source VARCHAR(20) NOT NULL,
            measured_at DATETIME NOT NULL,
            visite_id INT NOT NULL,
            signe_vital_id INT NOT NULL,
            measured_by_id BINARY(16) DEFAULT NULL,
            INDEX IDX_VISITE_MESURE_VISITE (visite_id),
            INDEX IDX_VISITE_MESURE_SIGNE (signe_vital_id),
            INDEX IDX_VISITE_MESURE_PERSONNEL (measured_by_id),
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE visite_mesure ADD CONSTRAINT FK_VISITE_MESURE_VISITE FOREIGN KEY (visite_id) REFERENCES visite (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE visite_mesure ADD CONSTRAINT FK_VISITE_MESURE_SIGNE FOREIGN KEY (signe_vital_id) REFERENCES signe_vital (id)');
        $this->addSql('ALTER TABLE visite_mesure ADD CONSTRAINT FK_VISITE_MESURE_PERSONNEL FOREIGN KEY (measured_by_id) REFERENCES personnel (id) ON DELETE SET NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE visite_mesure DROP FOREIGN KEY FK_VISITE_MESURE_VISITE');
        $this->addSql('ALTER TABLE visite_mesure DROP FOREIGN KEY FK_VISITE_MESURE_SIGNE');
        $this->addSql('ALTER TABLE visite_mesure DROP FOREIGN KEY FK_VISITE_MESURE_PERSONNEL');
        $this->addSql('DROP TABLE visite_mesure');

        $this->addSql('ALTER TABLE consultation DROP FOREIGN KEY FK_CONSULTATION_CLOSED_BY');
        $this->addSql('DROP INDEX IDX_CONSULTATION_CLOSED_BY ON consultation');
        $this->addSql('ALTER TABLE consultation
            DROP closed_by_id,
            DROP hospitalization_observation,
            DROP hospitalization_patient_opinion,
            DROP next_appointment_at,
            DROP wants_appointment,
            DROP needs_hospitalization,
            DROP complement_anamnese,
            DROP physical_exam');
        $this->addSql('ALTER TABLE consultation
            MODIFY motif VARCHAR(255) DEFAULT NULL,
            MODIFY histoire_maladie VARCHAR(255) DEFAULT NULL,
            MODIFY conduire_a_tenir VARCHAR(255) DEFAULT NULL,
            MODIFY consultation_observation VARCHAR(255) DEFAULT NULL');
    }
}
