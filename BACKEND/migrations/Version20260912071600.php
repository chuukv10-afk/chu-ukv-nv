<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260912071600 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Remet la première réception (stock d\'ouverture) au 28/08/2026.';
    }

    public function up(Schema $schema): void
    {
        if (!$schema->hasTable('reception')) {
            return;
        }

        $this->addSql("UPDATE reception r
            INNER JOIN (SELECT MIN(id) AS id FROM reception) first ON r.id = first.id
            SET r.date_reception = '2026-08-28',
                r.created_at = '2026-08-28 08:00:00'");

        if ($schema->hasTable('mouvement_stock')) {
            $this->addSql("UPDATE mouvement_stock ms
                INNER JOIN (SELECT MIN(id) AS id FROM reception) first ON ms.document_id = first.id
                SET ms.created_at = '2026-08-28 08:00:00'
                WHERE ms.document_type = 'RECEPTION'");
        }
    }

    public function down(Schema $schema): void
    {
    }
}
