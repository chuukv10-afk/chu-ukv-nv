<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260907030000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Demande service : visite hospitalisée + date de délivrance.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE demande_service ADD visite_id INT DEFAULT NULL, ADD delivree_at DATETIME DEFAULT NULL');
        $this->addSql('ALTER TABLE demande_service ADD CONSTRAINT FK_DEMANDE_SERVICE_VISITE FOREIGN KEY (visite_id) REFERENCES visite (id) ON DELETE SET NULL');
        $this->addSql('CREATE INDEX IDX_DEMANDE_SERVICE_VISITE ON demande_service (visite_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE demande_service DROP FOREIGN KEY FK_DEMANDE_SERVICE_VISITE');
        $this->addSql('DROP INDEX IDX_DEMANDE_SERVICE_VISITE ON demande_service');
        $this->addSql('ALTER TABLE demande_service DROP visite_id, DROP delivree_at');
    }
}
