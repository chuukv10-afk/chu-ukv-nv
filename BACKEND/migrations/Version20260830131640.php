<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260830131640 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Lie Patient et Dpi en relation OneToOne (patient_id sur dpi).';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE dpi ADD patient_id BINARY(16) DEFAULT NULL COMMENT \'(DC2Type:uuid)\'');
        $this->addSql('ALTER TABLE dpi ADD CONSTRAINT FK_ABCE1AE46B899279 FOREIGN KEY (patient_id) REFERENCES patient (id) ON DELETE CASCADE');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_ABCE1AE46B899279 ON dpi (patient_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE dpi DROP FOREIGN KEY FK_ABCE1AE46B899279');
        $this->addSql('DROP INDEX UNIQ_ABCE1AE46B899279 ON dpi');
        $this->addSql('ALTER TABLE dpi DROP patient_id');
    }
}
