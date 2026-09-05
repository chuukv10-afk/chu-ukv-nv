<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260904234500 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Visite : date de début d\'hospitalisation (durée de séjour).';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE visite ADD hospitalized_at DATETIME DEFAULT NULL');
        $this->addSql('UPDATE visite SET hospitalized_at = enter_at WHERE statut = \'HOSPITALISE\' AND hospitalized_at IS NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE visite DROP hospitalized_at');
    }
}
