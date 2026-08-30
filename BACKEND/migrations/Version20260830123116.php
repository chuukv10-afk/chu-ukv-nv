<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260830123116 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'UUID v7 sur entités exposées, relation Role-Permission, périmètre sur PersonnelRole, renommage permission.perimetre en module.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('SET FOREIGN_KEY_CHECKS=0');
        $this->addSql('DROP TABLE IF EXISTS role_permission');
        $this->addSql('DROP TABLE IF EXISTS personnel_role');
        $this->addSql('DROP TABLE IF EXISTS personnel_specialite');

        foreach (['role', 'permission', 'personnel', 'patient'] as $table) {
            $this->addSql(sprintf('ALTER TABLE %s DROP COLUMN IF EXISTS uuid_id', $table));
        }

        foreach (['role', 'permission', 'personnel', 'patient'] as $table) {
            $this->addSql(sprintf('ALTER TABLE %s MODIFY id INT NOT NULL', $table));
            $this->addSql(sprintf('ALTER TABLE %s ADD uuid_id BINARY(16) DEFAULT NULL COMMENT \'(DC2Type:uuid)\'', $table));
            $this->addSql(sprintf('UPDATE %s SET uuid_id = UNHEX(REPLACE(UUID(), \'-\', \'\')) WHERE uuid_id IS NULL', $table));
            $this->addSql(sprintf('ALTER TABLE %s MODIFY uuid_id BINARY(16) NOT NULL COMMENT \'(DC2Type:uuid)\'', $table));
            $this->addSql(sprintf('ALTER TABLE %s DROP PRIMARY KEY', $table));
            $this->addSql(sprintf('ALTER TABLE %s DROP COLUMN id', $table));
            $this->addSql(sprintf('ALTER TABLE %s CHANGE uuid_id id BINARY(16) NOT NULL COMMENT \'(DC2Type:uuid)\'', $table));
            $this->addSql(sprintf('ALTER TABLE %s ADD PRIMARY KEY (id)', $table));
        }

        $this->addSql('ALTER TABLE permission CHANGE perimetre module VARCHAR(30) NOT NULL');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_E04992AA77153098 ON permission (code)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_57698A6A77153098 ON role (code)');

        $this->addSql('CREATE TABLE personnel_specialite (personnel_id BINARY(16) NOT NULL COMMENT \'(DC2Type:uuid)\', specialite_id INT NOT NULL, INDEX IDX_E0BD251C1C109075 (personnel_id), INDEX IDX_E0BD251C2195E0F0 (specialite_id), PRIMARY KEY (personnel_id, specialite_id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE personnel_specialite ADD CONSTRAINT FK_E0BD251C1C109075 FOREIGN KEY (personnel_id) REFERENCES personnel (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE personnel_specialite ADD CONSTRAINT FK_E0BD251C2195E0F0 FOREIGN KEY (specialite_id) REFERENCES specialite (id) ON DELETE CASCADE');

        $this->addSql('CREATE TABLE personnel_role (id BINARY(16) NOT NULL COMMENT \'(DC2Type:uuid)\', perimetre VARCHAR(20) NOT NULL, created_at DATETIME NOT NULL, personnel_id BINARY(16) NOT NULL COMMENT \'(DC2Type:uuid)\', role_id BINARY(16) NOT NULL COMMENT \'(DC2Type:uuid)\', service_id INT DEFAULT NULL, departement_id INT DEFAULT NULL, INDEX IDX_9C439E1B1C109075 (personnel_id), INDEX IDX_9C439E1BD60322AC (role_id), INDEX IDX_9C439E1BED5CA9E6 (service_id), INDEX IDX_9C439E1BCCF9E01E (departement_id), UNIQUE INDEX uniq_personnel_role (personnel_id, role_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE personnel_role ADD CONSTRAINT FK_9C439E1B1C109075 FOREIGN KEY (personnel_id) REFERENCES personnel (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE personnel_role ADD CONSTRAINT FK_9C439E1BD60322AC FOREIGN KEY (role_id) REFERENCES role (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE personnel_role ADD CONSTRAINT FK_9C439E1BED5CA9E6 FOREIGN KEY (service_id) REFERENCES service (id)');
        $this->addSql('ALTER TABLE personnel_role ADD CONSTRAINT FK_9C439E1BCCF9E01E FOREIGN KEY (departement_id) REFERENCES departement (id)');

        $this->addSql('CREATE TABLE role_permission (role_id BINARY(16) NOT NULL COMMENT \'(DC2Type:uuid)\', permission_id BINARY(16) NOT NULL COMMENT \'(DC2Type:uuid)\', INDEX IDX_6F7DF886D60322AC (role_id), INDEX IDX_6F7DF886FED90CCA (permission_id), PRIMARY KEY (role_id, permission_id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE role_permission ADD CONSTRAINT FK_6F7DF886D60322AC FOREIGN KEY (role_id) REFERENCES role (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE role_permission ADD CONSTRAINT FK_6F7DF886FED90CCA FOREIGN KEY (permission_id) REFERENCES permission (id) ON DELETE CASCADE');

        $this->addSql('SET FOREIGN_KEY_CHECKS=1');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('SET FOREIGN_KEY_CHECKS=0');
        $this->addSql('DROP TABLE role_permission');
        $this->addSql('DROP TABLE personnel_role');
        $this->addSql('DROP TABLE personnel_specialite');
        $this->addSql('SET FOREIGN_KEY_CHECKS=1');

        $this->throwIrreversibleMigrationException('La conversion UUID vers identifiants entiers auto-incrémentés n\'est pas supportée.');
    }
}
