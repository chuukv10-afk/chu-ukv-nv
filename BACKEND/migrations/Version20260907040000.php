<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260907040000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Refresh tokens et journal d’idempotence sync offline.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE refresh_token (id INT AUTO_INCREMENT NOT NULL, token_hash VARCHAR(64) NOT NULL, expires_at DATETIME NOT NULL, created_at DATETIME NOT NULL, personnel_id BINARY(16) NOT NULL COMMENT \'(DC2Type:uuid)\', UNIQUE INDEX UNIQ_REFRESH_TOKEN_HASH (token_hash), INDEX IDX_REFRESH_TOKEN_PERSONNEL (personnel_id), INDEX IDX_REFRESH_TOKEN_EXPIRES (expires_at), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE refresh_token ADD CONSTRAINT FK_REFRESH_TOKEN_PERSONNEL FOREIGN KEY (personnel_id) REFERENCES personnel (id) ON DELETE CASCADE');

        $this->addSql('CREATE TABLE sync_mutation (id INT AUTO_INCREMENT NOT NULL, client_id VARCHAR(36) NOT NULL, module VARCHAR(40) NOT NULL, action VARCHAR(80) NOT NULL, status VARCHAR(20) NOT NULL, entity_type VARCHAR(40) DEFAULT NULL, entity_id VARCHAR(64) DEFAULT NULL, result_json LONGTEXT DEFAULT NULL, created_at DATETIME NOT NULL, created_by_id BINARY(16) DEFAULT NULL COMMENT \'(DC2Type:uuid)\', UNIQUE INDEX UNIQ_SYNC_MUTATION_CLIENT (client_id), INDEX IDX_SYNC_MUTATION_AUTHOR (created_by_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE sync_mutation ADD CONSTRAINT FK_SYNC_MUTATION_AUTHOR FOREIGN KEY (created_by_id) REFERENCES personnel (id) ON DELETE SET NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE refresh_token DROP FOREIGN KEY FK_REFRESH_TOKEN_PERSONNEL');
        $this->addSql('ALTER TABLE sync_mutation DROP FOREIGN KEY FK_SYNC_MUTATION_AUTHOR');
        $this->addSql('DROP TABLE refresh_token');
        $this->addSql('DROP TABLE sync_mutation');
    }
}
