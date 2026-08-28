<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260828230112 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE acte_financier (id INT AUTO_INCREMENT NOT NULL, code VARCHAR(8) NOT NULL, libelle VARCHAR(50) NOT NULL, tarif NUMERIC(10, 4) NOT NULL, unite VARCHAR(15) NOT NULL, statut VARCHAR(20) NOT NULL, created_at DATETIME NOT NULL, PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE acte_financier_visite (id INT AUTO_INCREMENT NOT NULL, quantite INT NOT NULL, tarif_unitaire NUMERIC(10, 4) NOT NULL, tarif_total NUMERIC(10, 4) NOT NULL, statut VARCHAR(20) NOT NULL, created_at DATETIME NOT NULL, acte_id INT NOT NULL, visite_id INT NOT NULL, INDEX IDX_E002B09CA767B8C7 (acte_id), INDEX IDX_E002B09CC1C5DC59 (visite_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE antecedent (id INT AUTO_INCREMENT NOT NULL, created_at DATETIME NOT NULL, type_id INT NOT NULL, maladie_id INT NOT NULL, dpi_id INT NOT NULL, INDEX IDX_3166BE7CC54C8C93 (type_id), INDEX IDX_3166BE7CB4B1C397 (maladie_id), INDEX IDX_3166BE7C45BA88B (dpi_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE bloc (id INT AUTO_INCREMENT NOT NULL, code VARCHAR(8) NOT NULL, libelle VARCHAR(50) NOT NULL, created_at DATETIME NOT NULL, chambre VARCHAR(8) NOT NULL, PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE chambre (id INT AUTO_INCREMENT NOT NULL, libelle VARCHAR(50) NOT NULL, code VARCHAR(8) NOT NULL, type VARCHAR(8) NOT NULL, created_at DATETIME NOT NULL, PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE consultation (id INT AUTO_INCREMENT NOT NULL, consulted_at DATETIME NOT NULL, debut_at DATETIME DEFAULT NULL, fin_at DATETIME DEFAULT NULL, type_consultation VARCHAR(20) DEFAULT NULL, motif VARCHAR(255) DEFAULT NULL, histoire_maladie VARCHAR(255) DEFAULT NULL, consultation_observation VARCHAR(255) DEFAULT NULL, conduire_a_tenir VARCHAR(255) DEFAULT NULL, statut VARCHAR(20) NOT NULL, visite_id INT NOT NULL, INDEX IDX_964685A6C1C5DC59 (visite_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE demande_examen (id INT AUTO_INCREMENT NOT NULL, demande_at DATETIME NOT NULL, statut VARCHAR(20) NOT NULL, resultat VARCHAR(255) DEFAULT NULL, fichier VARCHAR(255) DEFAULT NULL, note_medecin VARCHAR(255) DEFAULT NULL, examen_id INT NOT NULL, INDEX IDX_1308C9035C8659A (examen_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE departement (id INT AUTO_INCREMENT NOT NULL, code VARCHAR(8) NOT NULL, libelle VARCHAR(100) NOT NULL, type VARCHAR(20) NOT NULL, created_at DATETIME NOT NULL, PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE diagnostic (id INT AUTO_INCREMENT NOT NULL, type VARCHAR(20) DEFAULT NULL, certitude VARCHAR(20) NOT NULL, stade_evolution VARCHAR(20) NOT NULL, created_at DATETIME NOT NULL, remarque VARCHAR(255) DEFAULT NULL, consultation_id INT NOT NULL, demande_examen_id INT DEFAULT NULL, maladie_id INT NOT NULL, INDEX IDX_FA7C888962FF6CDF (consultation_id), INDEX IDX_FA7C888970E49F4F (demande_examen_id), INDEX IDX_FA7C8889B4B1C397 (maladie_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE dpi (id INT AUTO_INCREMENT NOT NULL, num_dossier VARCHAR(50) NOT NULL, created_at DATETIME NOT NULL, statut VARCHAR(20) NOT NULL, PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE examen (id INT AUTO_INCREMENT NOT NULL, code VARCHAR(8) NOT NULL, libelle VARCHAR(100) NOT NULL, created_at DATETIME NOT NULL, PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE grade (id INT AUTO_INCREMENT NOT NULL, code VARCHAR(8) NOT NULL, libelle VARCHAR(100) NOT NULL, created_at DATETIME NOT NULL, PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE lit (id INT AUTO_INCREMENT NOT NULL, code VARCHAR(8) NOT NULL, numero_lit VARCHAR(15) NOT NULL, created_at DATETIME NOT NULL, PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE maladie (id INT AUTO_INCREMENT NOT NULL, code_cim10 VARCHAR(15) NOT NULL, libelle VARCHAR(100) NOT NULL, chapitre VARCHAR(10) DEFAULT NULL, created_at DATETIME NOT NULL, PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE patient (id INT AUTO_INCREMENT NOT NULL, nom VARCHAR(50) NOT NULL, post_nom VARCHAR(50) NOT NULL, prenom VARCHAR(50) DEFAULT NULL, telephone VARCHAR(15) DEFAULT NULL, adresse VARCHAR(100) DEFAULT NULL, lieu_naissance VARCHAR(30) DEFAULT NULL, date_naissance DATE NOT NULL, sexe VARCHAR(1) NOT NULL, password VARCHAR(255) DEFAULT NULL, status VARCHAR(20) NOT NULL, groupe_sanguin VARCHAR(20) DEFAULT NULL, created_at DATETIME NOT NULL, personne_aprevenir VARCHAR(50) DEFAULT NULL, contact_aprevenir VARCHAR(20) DEFAULT NULL, PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE permission (id INT AUTO_INCREMENT NOT NULL, code VARCHAR(25) NOT NULL, libelle VARCHAR(255) DEFAULT NULL, description VARCHAR(300) DEFAULT NULL, perimetre VARCHAR(30) NOT NULL, created_at DATETIME NOT NULL, PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE personnel (id INT AUTO_INCREMENT NOT NULL, nom VARCHAR(50) NOT NULL, post_nom VARCHAR(50) NOT NULL, prenom VARCHAR(50) DEFAULT NULL, telephone VARCHAR(15) NOT NULL, adresse VARCHAR(100) DEFAULT NULL, lieu_naissance VARCHAR(50) DEFAULT NULL, sexe VARCHAR(1) NOT NULL, password VARCHAR(255) NOT NULL, status VARCHAR(20) NOT NULL, matricule VARCHAR(20) DEFAULT NULL, cnome VARCHAR(20) DEFAULT NULL, type VARCHAR(15) NOT NULL, created_at DATETIME NOT NULL, grade_id INT DEFAULT NULL, service_id INT DEFAULT NULL, INDEX IDX_A6BCF3DEFE19A1A8 (grade_id), INDEX IDX_A6BCF3DEED5CA9E6 (service_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE personnel_role (personnel_id INT NOT NULL, role_id INT NOT NULL, INDEX IDX_9C439E1B1C109075 (personnel_id), INDEX IDX_9C439E1BD60322AC (role_id), PRIMARY KEY (personnel_id, role_id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE personnel_specialite (personnel_id INT NOT NULL, specialite_id INT NOT NULL, INDEX IDX_E0BD251C1C109075 (personnel_id), INDEX IDX_E0BD251C2195E0F0 (specialite_id), PRIMARY KEY (personnel_id, specialite_id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE role (id INT AUTO_INCREMENT NOT NULL, code VARCHAR(8) NOT NULL, libelle VARCHAR(100) NOT NULL, created_at DATETIME NOT NULL, PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE service (id INT AUTO_INCREMENT NOT NULL, code VARCHAR(8) NOT NULL, libelle VARCHAR(100) NOT NULL, created_at DATETIME NOT NULL, departement_id INT NOT NULL, INDEX IDX_E19D9AD2CCF9E01E (departement_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE specialite (id INT AUTO_INCREMENT NOT NULL, code VARCHAR(8) NOT NULL, libelle VARCHAR(100) NOT NULL, created_at DATETIME NOT NULL, PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE type_antecedent (id INT AUTO_INCREMENT NOT NULL, code VARCHAR(8) NOT NULL, libelle VARCHAR(50) NOT NULL, created_at DATETIME NOT NULL, PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE type_examen (id INT AUTO_INCREMENT NOT NULL, code VARCHAR(8) NOT NULL, libelle VARCHAR(100) NOT NULL, created_at DATETIME NOT NULL, PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE visite (id INT AUTO_INCREMENT NOT NULL, type_visite VARCHAR(20) NOT NULL, statut VARCHAR(20) NOT NULL, enter_at DATETIME NOT NULL, sorted_at DATETIME DEFAULT NULL, sorted_prevu_at DATETIME DEFAULT NULL, dpi_id INT NOT NULL, lit_id INT DEFAULT NULL, service_id INT NOT NULL, INDEX IDX_B09C8CBB45BA88B (dpi_id), INDEX IDX_B09C8CBB278B5057 (lit_id), INDEX IDX_B09C8CBBED5CA9E6 (service_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE acte_financier_visite ADD CONSTRAINT FK_E002B09CA767B8C7 FOREIGN KEY (acte_id) REFERENCES acte_financier (id)');
        $this->addSql('ALTER TABLE acte_financier_visite ADD CONSTRAINT FK_E002B09CC1C5DC59 FOREIGN KEY (visite_id) REFERENCES visite (id)');
        $this->addSql('ALTER TABLE antecedent ADD CONSTRAINT FK_3166BE7CC54C8C93 FOREIGN KEY (type_id) REFERENCES type_antecedent (id)');
        $this->addSql('ALTER TABLE antecedent ADD CONSTRAINT FK_3166BE7CB4B1C397 FOREIGN KEY (maladie_id) REFERENCES maladie (id)');
        $this->addSql('ALTER TABLE antecedent ADD CONSTRAINT FK_3166BE7C45BA88B FOREIGN KEY (dpi_id) REFERENCES dpi (id)');
        $this->addSql('ALTER TABLE consultation ADD CONSTRAINT FK_964685A6C1C5DC59 FOREIGN KEY (visite_id) REFERENCES visite (id)');
        $this->addSql('ALTER TABLE demande_examen ADD CONSTRAINT FK_1308C9035C8659A FOREIGN KEY (examen_id) REFERENCES examen (id)');
        $this->addSql('ALTER TABLE diagnostic ADD CONSTRAINT FK_FA7C888962FF6CDF FOREIGN KEY (consultation_id) REFERENCES consultation (id)');
        $this->addSql('ALTER TABLE diagnostic ADD CONSTRAINT FK_FA7C888970E49F4F FOREIGN KEY (demande_examen_id) REFERENCES demande_examen (id)');
        $this->addSql('ALTER TABLE diagnostic ADD CONSTRAINT FK_FA7C8889B4B1C397 FOREIGN KEY (maladie_id) REFERENCES maladie (id)');
        $this->addSql('ALTER TABLE personnel ADD CONSTRAINT FK_A6BCF3DEFE19A1A8 FOREIGN KEY (grade_id) REFERENCES grade (id)');
        $this->addSql('ALTER TABLE personnel ADD CONSTRAINT FK_A6BCF3DEED5CA9E6 FOREIGN KEY (service_id) REFERENCES service (id)');
        $this->addSql('ALTER TABLE personnel_role ADD CONSTRAINT FK_9C439E1B1C109075 FOREIGN KEY (personnel_id) REFERENCES personnel (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE personnel_role ADD CONSTRAINT FK_9C439E1BD60322AC FOREIGN KEY (role_id) REFERENCES role (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE personnel_specialite ADD CONSTRAINT FK_E0BD251C1C109075 FOREIGN KEY (personnel_id) REFERENCES personnel (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE personnel_specialite ADD CONSTRAINT FK_E0BD251C2195E0F0 FOREIGN KEY (specialite_id) REFERENCES specialite (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE service ADD CONSTRAINT FK_E19D9AD2CCF9E01E FOREIGN KEY (departement_id) REFERENCES departement (id)');
        $this->addSql('ALTER TABLE visite ADD CONSTRAINT FK_B09C8CBB45BA88B FOREIGN KEY (dpi_id) REFERENCES dpi (id)');
        $this->addSql('ALTER TABLE visite ADD CONSTRAINT FK_B09C8CBB278B5057 FOREIGN KEY (lit_id) REFERENCES lit (id)');
        $this->addSql('ALTER TABLE visite ADD CONSTRAINT FK_B09C8CBBED5CA9E6 FOREIGN KEY (service_id) REFERENCES service (id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE acte_financier_visite DROP FOREIGN KEY FK_E002B09CA767B8C7');
        $this->addSql('ALTER TABLE acte_financier_visite DROP FOREIGN KEY FK_E002B09CC1C5DC59');
        $this->addSql('ALTER TABLE antecedent DROP FOREIGN KEY FK_3166BE7CC54C8C93');
        $this->addSql('ALTER TABLE antecedent DROP FOREIGN KEY FK_3166BE7CB4B1C397');
        $this->addSql('ALTER TABLE antecedent DROP FOREIGN KEY FK_3166BE7C45BA88B');
        $this->addSql('ALTER TABLE consultation DROP FOREIGN KEY FK_964685A6C1C5DC59');
        $this->addSql('ALTER TABLE demande_examen DROP FOREIGN KEY FK_1308C9035C8659A');
        $this->addSql('ALTER TABLE diagnostic DROP FOREIGN KEY FK_FA7C888962FF6CDF');
        $this->addSql('ALTER TABLE diagnostic DROP FOREIGN KEY FK_FA7C888970E49F4F');
        $this->addSql('ALTER TABLE diagnostic DROP FOREIGN KEY FK_FA7C8889B4B1C397');
        $this->addSql('ALTER TABLE personnel DROP FOREIGN KEY FK_A6BCF3DEFE19A1A8');
        $this->addSql('ALTER TABLE personnel DROP FOREIGN KEY FK_A6BCF3DEED5CA9E6');
        $this->addSql('ALTER TABLE personnel_role DROP FOREIGN KEY FK_9C439E1B1C109075');
        $this->addSql('ALTER TABLE personnel_role DROP FOREIGN KEY FK_9C439E1BD60322AC');
        $this->addSql('ALTER TABLE personnel_specialite DROP FOREIGN KEY FK_E0BD251C1C109075');
        $this->addSql('ALTER TABLE personnel_specialite DROP FOREIGN KEY FK_E0BD251C2195E0F0');
        $this->addSql('ALTER TABLE service DROP FOREIGN KEY FK_E19D9AD2CCF9E01E');
        $this->addSql('ALTER TABLE visite DROP FOREIGN KEY FK_B09C8CBB45BA88B');
        $this->addSql('ALTER TABLE visite DROP FOREIGN KEY FK_B09C8CBB278B5057');
        $this->addSql('ALTER TABLE visite DROP FOREIGN KEY FK_B09C8CBBED5CA9E6');
        $this->addSql('DROP TABLE acte_financier');
        $this->addSql('DROP TABLE acte_financier_visite');
        $this->addSql('DROP TABLE antecedent');
        $this->addSql('DROP TABLE bloc');
        $this->addSql('DROP TABLE chambre');
        $this->addSql('DROP TABLE consultation');
        $this->addSql('DROP TABLE demande_examen');
        $this->addSql('DROP TABLE departement');
        $this->addSql('DROP TABLE diagnostic');
        $this->addSql('DROP TABLE dpi');
        $this->addSql('DROP TABLE examen');
        $this->addSql('DROP TABLE grade');
        $this->addSql('DROP TABLE lit');
        $this->addSql('DROP TABLE maladie');
        $this->addSql('DROP TABLE patient');
        $this->addSql('DROP TABLE permission');
        $this->addSql('DROP TABLE personnel');
        $this->addSql('DROP TABLE personnel_role');
        $this->addSql('DROP TABLE personnel_specialite');
        $this->addSql('DROP TABLE role');
        $this->addSql('DROP TABLE service');
        $this->addSql('DROP TABLE specialite');
        $this->addSql('DROP TABLE type_antecedent');
        $this->addSql('DROP TABLE type_examen');
        $this->addSql('DROP TABLE visite');
    }
}
