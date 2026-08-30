<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260830133352 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajoute la traçabilité (createdBy, updatedBy, updatedAt) sur patient et dpi.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE patient ADD updated_at DATETIME DEFAULT NULL, ADD created_by_id BINARY(16) DEFAULT NULL COMMENT \'(DC2Type:uuid)\', ADD updated_by_id BINARY(16) DEFAULT NULL COMMENT \'(DC2Type:uuid)\'');
        $this->addSql('ALTER TABLE patient ADD CONSTRAINT FK_1ADAD7EBB03A8386 FOREIGN KEY (created_by_id) REFERENCES personnel (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE patient ADD CONSTRAINT FK_1ADAD7EB896DBBDE FOREIGN KEY (updated_by_id) REFERENCES personnel (id) ON DELETE SET NULL');
        $this->addSql('CREATE INDEX IDX_1ADAD7EBB03A8386 ON patient (created_by_id)');
        $this->addSql('CREATE INDEX IDX_1ADAD7EB896DBBDE ON patient (updated_by_id)');

        $this->addSql('ALTER TABLE dpi ADD updated_at DATETIME DEFAULT NULL, ADD created_by_id BINARY(16) DEFAULT NULL COMMENT \'(DC2Type:uuid)\', ADD updated_by_id BINARY(16) DEFAULT NULL COMMENT \'(DC2Type:uuid)\'');
        $this->addSql('ALTER TABLE dpi ADD CONSTRAINT FK_ABCE1AE4B03A8386 FOREIGN KEY (created_by_id) REFERENCES personnel (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE dpi ADD CONSTRAINT FK_ABCE1AE4896DBBDE FOREIGN KEY (updated_by_id) REFERENCES personnel (id) ON DELETE SET NULL');
        $this->addSql('CREATE INDEX IDX_ABCE1AE4B03A8386 ON dpi (created_by_id)');
        $this->addSql('CREATE INDEX IDX_ABCE1AE4896DBBDE ON dpi (updated_by_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE patient DROP FOREIGN KEY FK_1ADAD7EBB03A8386');
        $this->addSql('ALTER TABLE patient DROP FOREIGN KEY FK_1ADAD7EB896DBBDE');
        $this->addSql('DROP INDEX IDX_1ADAD7EBB03A8386 ON patient');
        $this->addSql('DROP INDEX IDX_1ADAD7EB896DBBDE ON patient');
        $this->addSql('ALTER TABLE patient DROP updated_at, DROP created_by_id, DROP updated_by_id');

        $this->addSql('ALTER TABLE dpi DROP FOREIGN KEY FK_ABCE1AE4B03A8386');
        $this->addSql('ALTER TABLE dpi DROP FOREIGN KEY FK_ABCE1AE4896DBBDE');
        $this->addSql('DROP INDEX IDX_ABCE1AE4B03A8386 ON dpi');
        $this->addSql('DROP INDEX IDX_ABCE1AE4896DBBDE ON dpi');
        $this->addSql('ALTER TABLE dpi DROP updated_at, DROP created_by_id, DROP updated_by_id');
    }
}
