<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260914090000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Module Imagerie : études, images S3, flag type_examen.imagerie.';
    }

    public function up(Schema $schema): void
    {
        if ($schema->hasTable('type_examen') && !$schema->getTable('type_examen')->hasColumn('imagerie')) {
            $this->addSql('ALTER TABLE type_examen ADD imagerie TINYINT(1) DEFAULT 0 NOT NULL');
        }

        $this->addSql('CREATE TABLE etude_imagerie (id INT AUTO_INCREMENT NOT NULL, numero VARCHAR(30) NOT NULL, statut VARCHAR(20) NOT NULL, indication LONGTEXT DEFAULT NULL, technique LONGTEXT DEFAULT NULL, constatations LONGTEXT DEFAULT NULL, conclusion LONGTEXT DEFAULT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', interprete_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', valide_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', patient_id BINARY(16) NOT NULL COMMENT \'(DC2Type:uuid)\', examen_id INT NOT NULL, consultation_id INT DEFAULT NULL, demande_examen_id INT DEFAULT NULL, created_by_id BINARY(16) DEFAULT NULL COMMENT \'(DC2Type:uuid)\', interprete_par_id BINARY(16) DEFAULT NULL COMMENT \'(DC2Type:uuid)\', valide_par_id BINARY(16) DEFAULT NULL COMMENT \'(DC2Type:uuid)\', UNIQUE INDEX UNIQ_ETUDE_IMG_NUMERO (numero), UNIQUE INDEX UNIQ_ETUDE_IMG_DEMANDE (demande_examen_id), INDEX IDX_ETUDE_IMG_PATIENT (patient_id), INDEX IDX_ETUDE_IMG_EXAMEN (examen_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE image_imagerie (id INT AUTO_INCREMENT NOT NULL, storage_key VARCHAR(180) NOT NULL, original_name VARCHAR(180) NOT NULL, mime_type VARCHAR(80) NOT NULL, size_bytes INT NOT NULL, uploaded_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', etude_id INT NOT NULL, uploaded_by_id BINARY(16) DEFAULT NULL COMMENT \'(DC2Type:uuid)\', INDEX IDX_IMG_IMG_ETUDE (etude_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');

        $this->addSql('ALTER TABLE etude_imagerie ADD CONSTRAINT FK_ETUDE_IMG_PATIENT FOREIGN KEY (patient_id) REFERENCES patient (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE etude_imagerie ADD CONSTRAINT FK_ETUDE_IMG_EXAMEN FOREIGN KEY (examen_id) REFERENCES examen (id)');
        $this->addSql('ALTER TABLE etude_imagerie ADD CONSTRAINT FK_ETUDE_IMG_CONSULT FOREIGN KEY (consultation_id) REFERENCES consultation (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE etude_imagerie ADD CONSTRAINT FK_ETUDE_IMG_DEMANDE FOREIGN KEY (demande_examen_id) REFERENCES demande_examen (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE etude_imagerie ADD CONSTRAINT FK_ETUDE_IMG_CREATED FOREIGN KEY (created_by_id) REFERENCES personnel (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE etude_imagerie ADD CONSTRAINT FK_ETUDE_IMG_INTERP FOREIGN KEY (interprete_par_id) REFERENCES personnel (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE etude_imagerie ADD CONSTRAINT FK_ETUDE_IMG_VALIDE FOREIGN KEY (valide_par_id) REFERENCES personnel (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE image_imagerie ADD CONSTRAINT FK_IMG_IMG_ETUDE FOREIGN KEY (etude_id) REFERENCES etude_imagerie (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE image_imagerie ADD CONSTRAINT FK_IMG_IMG_UPLOAD FOREIGN KEY (uploaded_by_id) REFERENCES personnel (id) ON DELETE SET NULL');

        $this->addSql("INSERT INTO type_examen (code, libelle, created_at, imagerie) SELECT 'IMAGERIE', 'Imagerie médicale', NOW(), 1 FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM type_examen WHERE code = 'IMAGERIE')");
        $this->addSql("INSERT INTO examen (code, libelle, created_at, type_examen_id) SELECT 'RXTHORAX', 'Radiographie du thorax', NOW(), t.id FROM type_examen t WHERE t.code = 'IMAGERIE' AND NOT EXISTS (SELECT 1 FROM examen WHERE code = 'RXTHORAX')");
        $this->addSql("INSERT INTO examen (code, libelle, created_at, type_examen_id) SELECT 'ECHOABDO', 'Échographie abdominale', NOW(), t.id FROM type_examen t WHERE t.code = 'IMAGERIE' AND NOT EXISTS (SELECT 1 FROM examen WHERE code = 'ECHOABDO')");
        $this->addSql("INSERT INTO examen (code, libelle, created_at, type_examen_id) SELECT 'SCANCRAN', 'Scanner crânien', NOW(), t.id FROM type_examen t WHERE t.code = 'IMAGERIE' AND NOT EXISTS (SELECT 1 FROM examen WHERE code = 'SCANCRAN')");
        $this->addSql("INSERT INTO examen (code, libelle, created_at, type_examen_id) SELECT 'IRMCERV', 'IRM cérébrale', NOW(), t.id FROM type_examen t WHERE t.code = 'IMAGERIE' AND NOT EXISTS (SELECT 1 FROM examen WHERE code = 'IRMCERV')");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE image_imagerie DROP FOREIGN KEY FK_IMG_IMG_ETUDE');
        $this->addSql('ALTER TABLE image_imagerie DROP FOREIGN KEY FK_IMG_IMG_UPLOAD');
        $this->addSql('ALTER TABLE etude_imagerie DROP FOREIGN KEY FK_ETUDE_IMG_PATIENT');
        $this->addSql('ALTER TABLE etude_imagerie DROP FOREIGN KEY FK_ETUDE_IMG_EXAMEN');
        $this->addSql('ALTER TABLE etude_imagerie DROP FOREIGN KEY FK_ETUDE_IMG_CONSULT');
        $this->addSql('ALTER TABLE etude_imagerie DROP FOREIGN KEY FK_ETUDE_IMG_DEMANDE');
        $this->addSql('ALTER TABLE etude_imagerie DROP FOREIGN KEY FK_ETUDE_IMG_CREATED');
        $this->addSql('ALTER TABLE etude_imagerie DROP FOREIGN KEY FK_ETUDE_IMG_INTERP');
        $this->addSql('ALTER TABLE etude_imagerie DROP FOREIGN KEY FK_ETUDE_IMG_VALIDE');
        $this->addSql('DROP TABLE image_imagerie');
        $this->addSql('DROP TABLE etude_imagerie');
        if ($schema->hasTable('type_examen') && $schema->getTable('type_examen')->hasColumn('imagerie')) {
            $this->addSql('ALTER TABLE type_examen DROP imagerie');
        }
    }
}
