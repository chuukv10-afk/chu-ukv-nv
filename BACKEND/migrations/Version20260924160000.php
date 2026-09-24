<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260924160000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Facture : service facturant par acte, règlement partiel/total.';
    }

    public function up(Schema $schema): void
    {
        if ($schema->hasTable('facture_ligne')) {
            $ligne = $schema->getTable('facture_ligne');
            if (!$ligne->hasColumn('service_id')) {
                $this->addSql('ALTER TABLE facture_ligne ADD service_id INT DEFAULT NULL');
                $this->addSql('CREATE INDEX IDX_FACTURE_LIGNE_SERVICE ON facture_ligne (service_id)');
                $this->addSql('ALTER TABLE facture_ligne ADD CONSTRAINT FK_FACTURE_LIGNE_SERVICE FOREIGN KEY (service_id) REFERENCES service (id) ON DELETE SET NULL');
            }
            if (!$ligne->hasColumn('service_libelle')) {
                $this->addSql('ALTER TABLE facture_ligne ADD service_libelle VARCHAR(120) DEFAULT NULL');
            }
        }

        if ($schema->hasTable('facture')) {
            $facture = $schema->getTable('facture');
            if (!$facture->hasColumn('montant_paye')) {
                $this->addSql("ALTER TABLE facture ADD montant_paye NUMERIC(12, 2) NOT NULL DEFAULT '0.00'");
            }
            if (!$facture->hasColumn('statut_paiement')) {
                $this->addSql("ALTER TABLE facture ADD statut_paiement VARCHAR(16) NOT NULL DEFAULT 'NON_PAYEE'");
                $this->addSql('CREATE INDEX IDX_FACTURE_PAIEMENT ON facture (statut_paiement)');
            }
        }

        if (!$schema->hasTable('facture_reglement')) {
            $this->addSql('CREATE TABLE facture_reglement (
                id INT AUTO_INCREMENT NOT NULL,
                facture_id INT NOT NULL,
                created_by_id BINARY(16) DEFAULT NULL COMMENT \'(DC2Type:uuid)\',
                updated_by_id BINARY(16) DEFAULT NULL COMMENT \'(DC2Type:uuid)\',
                montant NUMERIC(12, 2) NOT NULL,
                mode VARCHAR(20) NOT NULL,
                date_reglement DATE NOT NULL,
                notes VARCHAR(255) DEFAULT NULL,
                created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\',
                updated_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\',
                INDEX IDX_FACTURE_REGL_FACTURE (facture_id),
                INDEX IDX_FACTURE_REGL_CREATED_BY (created_by_id),
                INDEX IDX_FACTURE_REGL_UPDATED_BY (updated_by_id),
                PRIMARY KEY(id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
            $this->addSql('ALTER TABLE facture_reglement ADD CONSTRAINT FK_FACTURE_REGL_FACTURE FOREIGN KEY (facture_id) REFERENCES facture (id) ON DELETE CASCADE');
            $this->addSql('ALTER TABLE facture_reglement ADD CONSTRAINT FK_FACTURE_REGL_CREATED_BY FOREIGN KEY (created_by_id) REFERENCES personnel (id) ON DELETE SET NULL');
            $this->addSql('ALTER TABLE facture_reglement ADD CONSTRAINT FK_FACTURE_REGL_UPDATED_BY FOREIGN KEY (updated_by_id) REFERENCES personnel (id) ON DELETE SET NULL');
        }
    }

    public function down(Schema $schema): void
    {
        if ($schema->hasTable('facture_reglement')) {
            $this->addSql('ALTER TABLE facture_reglement DROP FOREIGN KEY FK_FACTURE_REGL_FACTURE');
            $this->addSql('ALTER TABLE facture_reglement DROP FOREIGN KEY FK_FACTURE_REGL_CREATED_BY');
            $this->addSql('DROP TABLE facture_reglement');
        }
        if ($schema->hasTable('facture_ligne')) {
            $ligne = $schema->getTable('facture_ligne');
            if ($ligne->hasColumn('service_id')) {
                $this->addSql('ALTER TABLE facture_ligne DROP FOREIGN KEY FK_FACTURE_LIGNE_SERVICE');
                $this->addSql('ALTER TABLE facture_ligne DROP service_id');
            }
            if ($ligne->hasColumn('service_libelle')) {
                $this->addSql('ALTER TABLE facture_ligne DROP service_libelle');
            }
        }
        if ($schema->hasTable('facture')) {
            $facture = $schema->getTable('facture');
            if ($facture->hasColumn('montant_paye')) {
                $this->addSql('ALTER TABLE facture DROP montant_paye');
            }
            if ($facture->hasColumn('statut_paiement')) {
                $this->addSql('ALTER TABLE facture DROP statut_paiement');
            }
        }
    }
}
