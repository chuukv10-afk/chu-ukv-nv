<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260830160109 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE dpi CHANGE patient_id patient_id BINARY(16) NOT NULL, CHANGE created_by_id created_by_id BINARY(16) DEFAULT NULL, CHANGE updated_by_id updated_by_id BINARY(16) DEFAULT NULL');
        $this->addSql('ALTER TABLE patient CHANGE id id BINARY(16) NOT NULL, CHANGE created_by_id created_by_id BINARY(16) DEFAULT NULL, CHANGE updated_by_id updated_by_id BINARY(16) DEFAULT NULL');
        $this->addSql('ALTER TABLE permission CHANGE code code VARCHAR(50) NOT NULL, CHANGE id id BINARY(16) NOT NULL');
        $this->addSql('ALTER TABLE personnel CHANGE id id BINARY(16) NOT NULL');
        $this->addSql('ALTER TABLE personnel_specialite CHANGE personnel_id personnel_id BINARY(16) NOT NULL');
        $this->addSql('ALTER TABLE personnel_role CHANGE id id BINARY(16) NOT NULL, CHANGE personnel_id personnel_id BINARY(16) NOT NULL, CHANGE role_id role_id BINARY(16) NOT NULL');
        $this->addSql('ALTER TABLE role CHANGE id id BINARY(16) NOT NULL');
        $this->addSql('ALTER TABLE role_permission CHANGE role_id role_id BINARY(16) NOT NULL, CHANGE permission_id permission_id BINARY(16) NOT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE dpi CHANGE patient_id patient_id BINARY(16) DEFAULT NULL COMMENT \'(DC2Type:uuid)\', CHANGE created_by_id created_by_id BINARY(16) DEFAULT NULL COMMENT \'(DC2Type:uuid)\', CHANGE updated_by_id updated_by_id BINARY(16) DEFAULT NULL COMMENT \'(DC2Type:uuid)\'');
        $this->addSql('ALTER TABLE patient CHANGE id id BINARY(16) NOT NULL COMMENT \'(DC2Type:uuid)\', CHANGE created_by_id created_by_id BINARY(16) DEFAULT NULL COMMENT \'(DC2Type:uuid)\', CHANGE updated_by_id updated_by_id BINARY(16) DEFAULT NULL COMMENT \'(DC2Type:uuid)\'');
        $this->addSql('ALTER TABLE permission CHANGE code code VARCHAR(25) NOT NULL, CHANGE id id BINARY(16) NOT NULL COMMENT \'(DC2Type:uuid)\'');
        $this->addSql('ALTER TABLE personnel CHANGE id id BINARY(16) NOT NULL COMMENT \'(DC2Type:uuid)\'');
        $this->addSql('ALTER TABLE personnel_role CHANGE id id BINARY(16) NOT NULL COMMENT \'(DC2Type:uuid)\', CHANGE personnel_id personnel_id BINARY(16) NOT NULL COMMENT \'(DC2Type:uuid)\', CHANGE role_id role_id BINARY(16) NOT NULL COMMENT \'(DC2Type:uuid)\'');
        $this->addSql('ALTER TABLE personnel_specialite CHANGE personnel_id personnel_id BINARY(16) NOT NULL COMMENT \'(DC2Type:uuid)\'');
        $this->addSql('ALTER TABLE role CHANGE id id BINARY(16) NOT NULL COMMENT \'(DC2Type:uuid)\'');
        $this->addSql('ALTER TABLE role_permission CHANGE role_id role_id BINARY(16) NOT NULL COMMENT \'(DC2Type:uuid)\', CHANGE permission_id permission_id BINARY(16) NOT NULL COMMENT \'(DC2Type:uuid)\'');
    }
}
