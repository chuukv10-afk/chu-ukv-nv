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
        if ($schema->hasTable('facture')) {
            $facture = $schema->getTable('facture');
            if (!$facture->hasColumn('montant_brut')) {
                $this->addSql("ALTER TABLE facture ADD montant_brut NUMERIC(12, 2) NOT NULL DEFAULT '0.00'");
            }
            if (!$facture->hasColumn('remise_type')) {
                $this->addSql("ALTER TABLE facture ADD remise_type VARCHAR(16) NOT NULL DEFAULT 'NONE'");
            }
            if (!$facture->hasColumn('remise_valeur')) {
                $this->addSql("ALTER TABLE facture ADD remise_valeur NUMERIC(12, 2) NOT NULL DEFAULT '0.00'");
            }
            if (!$facture->hasColumn('remise_montant')) {
                $this->addSql("ALTER TABLE facture ADD remise_montant NUMERIC(12, 2) NOT NULL DEFAULT '0.00'");
            }
            $this->addSql('UPDATE facture SET montant_brut = montant_total WHERE montant_brut = 0');
        }

        if ($schema->hasTable('facture_ligne')) {
            $ligne = $schema->getTable('facture_ligne');
            if (!$ligne->hasColumn('tarif_brut')) {
                $this->addSql("ALTER TABLE facture_ligne ADD tarif_brut NUMERIC(12, 2) NOT NULL DEFAULT '0.00'");
            }
            if (!$ligne->hasColumn('remise_type')) {
                $this->addSql("ALTER TABLE facture_ligne ADD remise_type VARCHAR(16) NOT NULL DEFAULT 'NONE'");
            }
            if (!$ligne->hasColumn('remise_valeur')) {
                $this->addSql("ALTER TABLE facture_ligne ADD remise_valeur NUMERIC(12, 2) NOT NULL DEFAULT '0.00'");
            }
            if (!$ligne->hasColumn('remise_montant')) {
                $this->addSql("ALTER TABLE facture_ligne ADD remise_montant NUMERIC(12, 2) NOT NULL DEFAULT '0.00'");
            }
            $this->addSql('UPDATE facture_ligne SET tarif_brut = tarif_total WHERE tarif_brut = 0');
        }
    }

    public function down(Schema $schema): void
    {
        if ($schema->hasTable('facture')) {
            $facture = $schema->getTable('facture');
            if ($facture->hasColumn('montant_brut')) {
                $this->addSql('ALTER TABLE facture DROP montant_brut');
            }
            if ($facture->hasColumn('remise_type')) {
                $this->addSql('ALTER TABLE facture DROP remise_type');
            }
            if ($facture->hasColumn('remise_valeur')) {
                $this->addSql('ALTER TABLE facture DROP remise_valeur');
            }
            if ($facture->hasColumn('remise_montant')) {
                $this->addSql('ALTER TABLE facture DROP remise_montant');
            }
        }

        if ($schema->hasTable('facture_ligne')) {
            $ligne = $schema->getTable('facture_ligne');
            if ($ligne->hasColumn('tarif_brut')) {
                $this->addSql('ALTER TABLE facture_ligne DROP tarif_brut');
            }
            if ($ligne->hasColumn('remise_type')) {
                $this->addSql('ALTER TABLE facture_ligne DROP remise_type');
            }
            if ($ligne->hasColumn('remise_valeur')) {
                $this->addSql('ALTER TABLE facture_ligne DROP remise_valeur');
            }
            if ($ligne->hasColumn('remise_montant')) {
                $this->addSql('ALTER TABLE facture_ligne DROP remise_montant');
            }
        }
    }
}
