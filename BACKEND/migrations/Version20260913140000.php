<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260913140000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajoute le montant déjà encaissé sur les demandes de service (encaissement partiel).';
    }

    public function up(Schema $schema): void
    {
        if (!$schema->hasTable('demande_service')) {
            return;
        }
        $table = $schema->getTable('demande_service');
        if (!$table->hasColumn('montant_paye')) {
            $this->addSql('ALTER TABLE demande_service ADD montant_paye NUMERIC(12, 4) NOT NULL DEFAULT 0.0000');
        }
        $this->addSql("UPDATE demande_service SET montant_paye = montant_total WHERE statut_paiement = 'PAYEE'");
    }

    public function down(Schema $schema): void
    {
        if ($schema->hasTable('demande_service') && $schema->getTable('demande_service')->hasColumn('montant_paye')) {
            $this->addSql('ALTER TABLE demande_service DROP montant_paye');
        }
    }
}
