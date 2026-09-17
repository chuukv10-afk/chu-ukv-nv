<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260916030000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Paie prime locale : barème, périodes mensuelles et lignes snapshot.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE bareme_prime (
            id INT AUTO_INCREMENT NOT NULL,
            grade_id INT DEFAULT NULL,
            fonction_id INT NOT NULL,
            montant NUMERIC(12, 2) NOT NULL,
            date_effet DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            UNIQUE INDEX UNIQ_BAREME_PRIME_GRADE_FONCTION (grade_id, fonction_id),
            INDEX IDX_BAREME_PRIME_GRADE (grade_id),
            INDEX IDX_BAREME_PRIME_FONCTION (fonction_id),
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');

        $this->addSql('CREATE TABLE paie_periode (
            id INT AUTO_INCREMENT NOT NULL,
            created_by_id BINARY(16) DEFAULT NULL COMMENT \'(DC2Type:uuid)\',
            valide_par_id BINARY(16) DEFAULT NULL COMMENT \'(DC2Type:uuid)\',
            annee INT NOT NULL,
            mois INT NOT NULL,
            statut VARCHAR(20) NOT NULL,
            created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            generated_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            total_net NUMERIC(14, 2) NOT NULL,
            valide_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            UNIQUE INDEX UNIQ_PAIE_PERIODE_MOIS (annee, mois),
            INDEX IDX_PAIE_PERIODE_CREATED_BY (created_by_id),
            INDEX IDX_PAIE_PERIODE_VALIDE_PAR (valide_par_id),
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');

        $this->addSql('CREATE TABLE paie_ligne (
            id INT AUTO_INCREMENT NOT NULL,
            periode_id INT NOT NULL,
            personnel_id BINARY(16) DEFAULT NULL COMMENT \'(DC2Type:uuid)\',
            nom_complet VARCHAR(160) NOT NULL,
            matricule VARCHAR(20) DEFAULT NULL,
            grade_libelle VARCHAR(100) DEFAULT NULL,
            fonction_libelle VARCHAR(150) DEFAULT NULL,
            service_libelle VARCHAR(100) DEFAULT NULL,
            statut_personnel VARCHAR(20) DEFAULT NULL,
            montant NUMERIC(12, 2) NOT NULL,
            source VARCHAR(20) NOT NULL,
            inclus TINYINT(1) NOT NULL,
            motif_code VARCHAR(30) DEFAULT NULL,
            motif VARCHAR(255) DEFAULT NULL,
            UNIQUE INDEX UNIQ_PAIE_LIGNE_PERIODE_PERSONNEL (periode_id, personnel_id),
            INDEX IDX_PAIE_LIGNE_PERIODE (periode_id),
            INDEX IDX_PAIE_LIGNE_PERSONNEL (personnel_id),
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');

        $this->addSql('ALTER TABLE bareme_prime ADD CONSTRAINT FK_BAREME_PRIME_GRADE FOREIGN KEY (grade_id) REFERENCES grade (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE bareme_prime ADD CONSTRAINT FK_BAREME_PRIME_FONCTION FOREIGN KEY (fonction_id) REFERENCES fonction (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE paie_periode ADD CONSTRAINT FK_PAIE_PERIODE_CREATED_BY FOREIGN KEY (created_by_id) REFERENCES personnel (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE paie_periode ADD CONSTRAINT FK_PAIE_PERIODE_VALIDE_PAR FOREIGN KEY (valide_par_id) REFERENCES personnel (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE paie_ligne ADD CONSTRAINT FK_PAIE_LIGNE_PERIODE FOREIGN KEY (periode_id) REFERENCES paie_periode (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE paie_ligne ADD CONSTRAINT FK_PAIE_LIGNE_PERSONNEL FOREIGN KEY (personnel_id) REFERENCES personnel (id) ON DELETE SET NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE paie_ligne DROP FOREIGN KEY FK_PAIE_LIGNE_PERIODE');
        $this->addSql('ALTER TABLE paie_ligne DROP FOREIGN KEY FK_PAIE_LIGNE_PERSONNEL');
        $this->addSql('ALTER TABLE paie_periode DROP FOREIGN KEY FK_PAIE_PERIODE_CREATED_BY');
        $this->addSql('ALTER TABLE paie_periode DROP FOREIGN KEY FK_PAIE_PERIODE_VALIDE_PAR');
        $this->addSql('ALTER TABLE bareme_prime DROP FOREIGN KEY FK_BAREME_PRIME_GRADE');
        $this->addSql('ALTER TABLE bareme_prime DROP FOREIGN KEY FK_BAREME_PRIME_FONCTION');
        $this->addSql('DROP TABLE paie_ligne');
        $this->addSql('DROP TABLE paie_periode');
        $this->addSql('DROP TABLE bareme_prime');
    }
}
