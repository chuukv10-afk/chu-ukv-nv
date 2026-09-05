<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260905003000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Consultation : fiche d\'évolution du tour de salle (JSON).';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE consultation ADD evolution_sheet JSON DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE consultation DROP evolution_sheet');
    }
}
