<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260918080000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Lien UKV sur le patient : code étudiant unique et filière.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE patient ADD code_ukv VARCHAR(20) DEFAULT NULL');
        $this->addSql('ALTER TABLE patient ADD filiere_id INT DEFAULT NULL');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_PATIENT_CODE_UKV ON patient (code_ukv)');
        $this->addSql('CREATE INDEX IDX_PATIENT_FILIERE ON patient (filiere_id)');
        $this->addSql('ALTER TABLE patient ADD CONSTRAINT FK_PATIENT_FILIERE FOREIGN KEY (filiere_id) REFERENCES filiere (id) ON DELETE SET NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE patient DROP FOREIGN KEY FK_PATIENT_FILIERE');
        $this->addSql('DROP INDEX UNIQ_PATIENT_CODE_UKV ON patient');
        $this->addSql('DROP INDEX IDX_PATIENT_FILIERE ON patient');
        $this->addSql('ALTER TABLE patient DROP code_ukv, DROP filiere_id');
    }
}
