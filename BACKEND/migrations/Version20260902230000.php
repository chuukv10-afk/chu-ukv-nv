<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260902230000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Restructure Bloc → Chambre → Lit et lie Examen à TypeExamen (catégorie).';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE chambre ADD bloc_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE lit ADD chambre_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE examen ADD type_examen_id INT DEFAULT NULL');

        $this->addSql('UPDATE chambre c INNER JOIN bloc b ON UPPER(b.chambre) = UPPER(c.code) SET c.bloc_id = b.id');

        $this->addSql("INSERT INTO bloc (code, libelle, created_at)
            SELECT 'DEFAULT', 'Bloc par défaut', NOW()
            FROM DUAL
            WHERE NOT EXISTS (SELECT 1 FROM bloc WHERE code = 'DEFAULT')");

        $this->addSql("UPDATE chambre SET bloc_id = (SELECT id FROM bloc ORDER BY id ASC LIMIT 1) WHERE bloc_id IS NULL");

        $this->addSql("INSERT INTO type_examen (code, libelle, created_at)
            SELECT 'DEFAULT', 'Catégorie par défaut', NOW()
            WHERE NOT EXISTS (SELECT 1 FROM type_examen)");

        $this->addSql('UPDATE examen e SET type_examen_id = (SELECT MIN(t.id) FROM type_examen t) WHERE e.type_examen_id IS NULL');

        $this->addSql('UPDATE lit SET chambre_id = (SELECT MIN(c.id) FROM chambre c) WHERE chambre_id IS NULL');

        $this->addSql('ALTER TABLE bloc DROP chambre');

        $this->addSql('ALTER TABLE chambre CHANGE bloc_id bloc_id INT NOT NULL');
        $this->addSql('ALTER TABLE chambre ADD CONSTRAINT FK_C509E4FF5597D8 FOREIGN KEY (bloc_id) REFERENCES bloc (id)');
        $this->addSql('CREATE INDEX IDX_C509E4FF5597D8 ON chambre (bloc_id)');

        $this->addSql('ALTER TABLE lit CHANGE chambre_id chambre_id INT NOT NULL');
        $this->addSql('ALTER TABLE lit ADD CONSTRAINT FK_6DAAE7439B177FBC FOREIGN KEY (chambre_id) REFERENCES chambre (id)');
        $this->addSql('CREATE INDEX IDX_6DAAE7439B177FBC ON lit (chambre_id)');

        $this->addSql('ALTER TABLE examen CHANGE type_examen_id type_examen_id INT NOT NULL');
        $this->addSql('ALTER TABLE examen ADD CONSTRAINT FK_514C8FAC1B65292 FOREIGN KEY (type_examen_id) REFERENCES type_examen (id)');
        $this->addSql('CREATE INDEX IDX_514C8FAC1B65292 ON examen (type_examen_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE examen DROP FOREIGN KEY FK_514C8FAC1B65292');
        $this->addSql('DROP INDEX IDX_514C8FAC1B65292 ON examen');
        $this->addSql('ALTER TABLE examen DROP type_examen_id');

        $this->addSql('ALTER TABLE lit DROP FOREIGN KEY FK_6DAAE7439B177FBC');
        $this->addSql('DROP INDEX IDX_6DAAE7439B177FBC ON lit');
        $this->addSql('ALTER TABLE lit DROP chambre_id');

        $this->addSql('ALTER TABLE chambre DROP FOREIGN KEY FK_C509E4FF5597D8');
        $this->addSql('DROP INDEX IDX_C509E4FF5597D8 ON chambre');
        $this->addSql('ALTER TABLE chambre DROP bloc_id');

        $this->addSql('ALTER TABLE bloc ADD chambre VARCHAR(8) NOT NULL DEFAULT \'\'');
        $this->addSql('UPDATE bloc b INNER JOIN chambre c ON c.bloc_id = b.id SET b.chambre = c.code');
        $this->addSql('ALTER TABLE bloc ALTER chambre DROP DEFAULT');
    }
}
