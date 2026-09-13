<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260913153000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Date réelle d’un approvisionnement de service antérieur.';
    }

    public function up(Schema $schema): void
    {
        if (!$schema->hasTable('demande_service')) {
            return;
        }
        $table = $schema->getTable('demande_service');
        if (!$table->hasColumn('date_livraison')) {
            $this->addSql('ALTER TABLE demande_service ADD date_livraison DATETIME DEFAULT NULL');
        }
    }

    public function down(Schema $schema): void
    {
        if ($schema->hasTable('demande_service') && $schema->getTable('demande_service')->hasColumn('date_livraison')) {
            $this->addSql('ALTER TABLE demande_service DROP date_livraison');
        }
    }
}
