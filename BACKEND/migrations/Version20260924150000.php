<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Types\IntegerType;
use Doctrine\Migrations\AbstractMigration;

final class Version20260924150000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Facture : created_by_id / updated_by_id en UUID (personnel).';
    }

    public function up(Schema $schema): void
    {
        if (!$schema->hasTable('facture')) {
            return;
        }

        $table = $schema->getTable('facture');
        $this->convertPersonnelUuidColumn($table, 'facture', 'created_by_id', 'FK_FACTURE_CREATED_BY');
        $this->convertPersonnelUuidColumn($table, 'facture', 'updated_by_id', 'FK_FACTURE_UPDATED_BY');
    }

    public function down(Schema $schema): void
    {
        if (!$schema->hasTable('facture')) {
            return;
        }

        $table = $schema->getTable('facture');
        foreach (['created_by_id' => 'FK_FACTURE_CREATED_BY', 'updated_by_id' => 'FK_FACTURE_UPDATED_BY'] as $column => $fkName) {
            $fk = $this->foreignKeyNameOnColumn($table, $column);
            if (null !== $fk) {
                $this->addSql('ALTER TABLE facture DROP FOREIGN KEY ' . $fk);
            }
            if ($table->hasColumn($column) && !$table->getColumn($column)->getType() instanceof IntegerType) {
                $this->addSql(sprintf('ALTER TABLE facture CHANGE %s %s INT DEFAULT NULL', $column, $column));
            }
            if (null === $this->foreignKeyNameOnColumn($table, $column)) {
                $this->addSql(sprintf(
                    'ALTER TABLE facture ADD CONSTRAINT %s FOREIGN KEY (%s) REFERENCES personnel (id) ON DELETE SET NULL',
                    $fkName,
                    $column,
                ));
            }
        }
    }

    private function convertPersonnelUuidColumn(Table $table, string $tableName, string $column, string $fkName): void
    {
        $isInteger = $table->hasColumn($column) && $table->getColumn($column)->getType() instanceof IntegerType;
        $fk = $this->foreignKeyNameOnColumn($table, $column);
        $needsFk = null === $fk;

        if ($isInteger && null !== $fk) {
            $this->addSql(sprintf('ALTER TABLE %s DROP FOREIGN KEY %s', $tableName, $fk));
            $needsFk = true;
        }

        if (!$table->hasColumn($column)) {
            $this->addSql(sprintf(
                'ALTER TABLE %s ADD %s BINARY(16) DEFAULT NULL COMMENT \'(DC2Type:uuid)\'',
                $tableName,
                $column,
            ));
            $needsFk = true;
        } elseif ($isInteger) {
            $this->addSql(sprintf('UPDATE %s SET %s = NULL', $tableName, $column));
            $this->addSql(sprintf(
                'ALTER TABLE %s CHANGE %s %s BINARY(16) DEFAULT NULL COMMENT \'(DC2Type:uuid)\'',
                $tableName,
                $column,
                $column,
            ));
            $needsFk = true;
        }

        if ($needsFk) {
            $this->addSql(sprintf(
                'ALTER TABLE %s ADD CONSTRAINT %s FOREIGN KEY (%s) REFERENCES personnel (id) ON DELETE SET NULL',
                $tableName,
                $fkName,
                $column,
            ));
        }
    }

    private function foreignKeyNameOnColumn(Table $table, string $column): ?string
    {
        foreach ($table->getForeignKeys() as $foreignKey) {
            if ($foreignKey->getLocalColumns() === [$column]) {
                return $foreignKey->getName();
            }
        }

        return null;
    }
}
