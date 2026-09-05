<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260904220000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'DemandeExamen : lien Consultation + prescripteur, resultat 1500 car.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('UPDATE diagnostic SET demande_examen_id = NULL');
        $this->addSql('DELETE FROM demande_examen');

        $this->addSql('ALTER TABLE demande_examen
            MODIFY resultat VARCHAR(1500) DEFAULT NULL,
            ADD consultation_id INT NOT NULL,
            ADD prescripteur_id BINARY(16) DEFAULT NULL');
        $this->addSql('CREATE INDEX IDX_DEMANDE_EXAMEN_CONSULTATION ON demande_examen (consultation_id)');
        $this->addSql('CREATE INDEX IDX_DEMANDE_EXAMEN_PRESCRIPTEUR ON demande_examen (prescripteur_id)');
        $this->addSql('ALTER TABLE demande_examen ADD CONSTRAINT FK_DEMANDE_EXAMEN_CONSULTATION
            FOREIGN KEY (consultation_id) REFERENCES consultation (id)');
        $this->addSql('ALTER TABLE demande_examen ADD CONSTRAINT FK_DEMANDE_EXAMEN_PRESCRIPTEUR
            FOREIGN KEY (prescripteur_id) REFERENCES personnel (id) ON DELETE SET NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE demande_examen DROP FOREIGN KEY FK_DEMANDE_EXAMEN_CONSULTATION');
        $this->addSql('ALTER TABLE demande_examen DROP FOREIGN KEY FK_DEMANDE_EXAMEN_PRESCRIPTEUR');
        $this->addSql('DROP INDEX IDX_DEMANDE_EXAMEN_CONSULTATION ON demande_examen');
        $this->addSql('DROP INDEX IDX_DEMANDE_EXAMEN_PRESCRIPTEUR ON demande_examen');
        $this->addSql('ALTER TABLE demande_examen
            DROP consultation_id,
            DROP prescripteur_id,
            MODIFY resultat VARCHAR(255) DEFAULT NULL');
    }
}
