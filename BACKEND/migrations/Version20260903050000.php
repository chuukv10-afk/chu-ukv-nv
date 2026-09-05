<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260903050000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Consultation : auteur (opened_by) et correction statut ANNULEE';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE consultation ADD opened_by_id BINARY(16) DEFAULT NULL');
        $this->addSql('CREATE INDEX IDX_CONSULTATION_OPENED_BY ON consultation (opened_by_id)');
        $this->addSql('ALTER TABLE consultation ADD CONSTRAINT FK_CONSULTATION_OPENED_BY FOREIGN KEY (opened_by_id) REFERENCES personnel (id) ON DELETE SET NULL');
        $this->addSql("UPDATE consultation SET statut = 'ANNULEE' WHERE statut = 'ANNULE'");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE consultation DROP FOREIGN KEY FK_CONSULTATION_OPENED_BY');
        $this->addSql('DROP INDEX IDX_CONSULTATION_OPENED_BY ON consultation');
        $this->addSql('ALTER TABLE consultation DROP opened_by_id');
        $this->addSql("UPDATE consultation SET statut = 'ANNULE' WHERE statut = 'ANNULEE'");
    }
}
