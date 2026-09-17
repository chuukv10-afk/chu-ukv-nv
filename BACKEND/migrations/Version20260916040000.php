<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260916040000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Paie : snapshot du barème par période et proposition automatique sur chaque ligne.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE paie_ligne ADD montant_propose NUMERIC(12, 2) DEFAULT NULL');
        $this->addSql('CREATE TABLE paie_bareme_applique (
            id INT AUTO_INCREMENT NOT NULL,
            periode_id INT NOT NULL,
            grade_id INT DEFAULT NULL,
            fonction_id INT DEFAULT NULL,
            grade_libelle VARCHAR(100) DEFAULT NULL,
            fonction_libelle VARCHAR(150) NOT NULL,
            montant NUMERIC(12, 2) NOT NULL,
            INDEX IDX_PAIE_BAREME_APPLIQUE_PERIODE (periode_id),
            INDEX IDX_PAIE_BAREME_APPLIQUE_GRADE (grade_id),
            INDEX IDX_PAIE_BAREME_APPLIQUE_FONCTION (fonction_id),
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE paie_bareme_applique ADD CONSTRAINT FK_PAIE_BAREME_APPLIQUE_PERIODE FOREIGN KEY (periode_id) REFERENCES paie_periode (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE paie_bareme_applique ADD CONSTRAINT FK_PAIE_BAREME_APPLIQUE_GRADE FOREIGN KEY (grade_id) REFERENCES grade (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE paie_bareme_applique ADD CONSTRAINT FK_PAIE_BAREME_APPLIQUE_FONCTION FOREIGN KEY (fonction_id) REFERENCES fonction (id) ON DELETE SET NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE paie_bareme_applique DROP FOREIGN KEY FK_PAIE_BAREME_APPLIQUE_PERIODE');
        $this->addSql('ALTER TABLE paie_bareme_applique DROP FOREIGN KEY FK_PAIE_BAREME_APPLIQUE_GRADE');
        $this->addSql('ALTER TABLE paie_bareme_applique DROP FOREIGN KEY FK_PAIE_BAREME_APPLIQUE_FONCTION');
        $this->addSql('DROP TABLE paie_bareme_applique');
        $this->addSql('ALTER TABLE paie_ligne DROP montant_propose');
    }
}
