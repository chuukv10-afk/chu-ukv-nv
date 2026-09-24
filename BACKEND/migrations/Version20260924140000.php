<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260924140000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Remises globale et par acte sur les factures.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE facture ADD montant_brut NUMERIC(12, 2) NOT NULL DEFAULT '0.00'");
        $this->addSql("ALTER TABLE facture ADD remise_type VARCHAR(16) NOT NULL DEFAULT 'NONE'");
        $this->addSql("ALTER TABLE facture ADD remise_valeur NUMERIC(12, 2) NOT NULL DEFAULT '0.00'");
        $this->addSql("ALTER TABLE facture ADD remise_montant NUMERIC(12, 2) NOT NULL DEFAULT '0.00'");
        $this->addSql("ALTER TABLE facture_ligne ADD tarif_brut NUMERIC(12, 2) NOT NULL DEFAULT '0.00'");
        $this->addSql("ALTER TABLE facture_ligne ADD remise_type VARCHAR(16) NOT NULL DEFAULT 'NONE'");
        $this->addSql("ALTER TABLE facture_ligne ADD remise_valeur NUMERIC(12, 2) NOT NULL DEFAULT '0.00'");
        $this->addSql("ALTER TABLE facture_ligne ADD remise_montant NUMERIC(12, 2) NOT NULL DEFAULT '0.00'");
        $this->addSql('UPDATE facture_ligne SET tarif_brut = tarif_total WHERE tarif_brut = 0');
        $this->addSql('UPDATE facture SET montant_brut = montant_total WHERE montant_brut = 0');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE facture DROP montant_brut, DROP remise_type, DROP remise_valeur, DROP remise_montant');
        $this->addSql('ALTER TABLE facture_ligne DROP tarif_brut, DROP remise_type, DROP remise_valeur, DROP remise_montant');
    }
}
