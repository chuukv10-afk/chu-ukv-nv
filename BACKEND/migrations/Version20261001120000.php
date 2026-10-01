<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261001120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Grille tarifaire : origine importée ou ajoutée manuellement.';
    }

    public function up(Schema $schema): void
    {
        if ($schema->hasTable('acte_financier') && !$schema->getTable('acte_financier')->hasColumn('origine')) {
            $this->addSql("ALTER TABLE acte_financier ADD origine VARCHAR(16) NOT NULL DEFAULT 'GRILLE'");
        }
    }

    public function down(Schema $schema): void
    {
        if ($schema->hasTable('acte_financier') && $schema->getTable('acte_financier')->hasColumn('origine')) {
            $this->addSql('ALTER TABLE acte_financier DROP origine');
        }
    }
}
