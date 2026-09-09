<?php

namespace App\Service\Admin;

use App\Exception\ConflictException;
use Doctrine\DBAL\Connection;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

final class DatabaseAdminService
{
    private const PROTECTED_TABLES = [
        'doctrine_migration_versions',
    ];

    private const EXCEL_MAX_ROWS = 10000;

    private const BLOCKED_SQL = [
        'DROP DATABASE',
        'CREATE DATABASE',
        'ALTER DATABASE',
        'GRANT ',
        'REVOKE ',
        'CREATE USER',
        'DROP USER',
        'LOAD_FILE',
        'INTO OUTFILE',
        'INTO DUMPFILE',
        'SLEEP(',
        'BENCHMARK(',
    ];

    public function __construct(
        private readonly Connection $connection,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function overview(): array
    {
        $schema = $this->databaseName();
        $tables = $this->connection->fetchAllAssociative(
            'SELECT TABLE_NAME AS name,
                    TABLE_ROWS AS rowEstimate,
                    DATA_LENGTH + INDEX_LENGTH AS sizeBytes,
                    ENGINE AS engine,
                    TABLE_COLLATION AS collationName,
                    CREATE_TIME AS createdAt,
                    UPDATE_TIME AS updatedAt
             FROM information_schema.TABLES
             WHERE TABLE_SCHEMA = :schema AND TABLE_TYPE = \'BASE TABLE\'
             ORDER BY TABLE_NAME ASC',
            ['schema' => $schema],
        );

        $totalSize = 0;
        $items = [];
        foreach ($tables as $table) {
            $size = (int) ($table['sizeBytes'] ?? 0);
            $totalSize += $size;
            $name = (string) $table['name'];
            $items[] = [
                'name' => $name,
                'rowEstimate' => (int) ($table['rowEstimate'] ?? 0),
                'sizeBytes' => $size,
                'sizeLabel' => $this->formatBytes($size),
                'engine' => $table['engine'],
                'collation' => $table['collationName'],
                'protected' => $this->isProtected($name),
            ];
        }

        return [
            'database' => $schema,
            'tableCount' => count($items),
            'sizeBytes' => $totalSize,
            'sizeLabel' => $this->formatBytes($totalSize),
            'protectedTables' => self::PROTECTED_TABLES,
            'tables' => $items,
        ];
    }

    /**
     * @param list<string> $tables
     * @return array{truncated: list<string>}
     */
    public function truncate(array $tables): array
    {
        $names = $this->resolveExistingTables($tables);
        foreach ($names as $name) {
            if ($this->isProtected($name)) {
                throw new ConflictException(sprintf('La table « %s » est protégée et ne peut pas être vidée.', $name));
            }
        }

        $this->connection->executeStatement('SET FOREIGN_KEY_CHECKS = 0');
        try {
            foreach ($names as $name) {
                $this->connection->executeStatement('TRUNCATE TABLE ' . $this->quoteName($name));
            }
        } finally {
            $this->connection->executeStatement('SET FOREIGN_KEY_CHECKS = 1');
        }

        return ['truncated' => $names];
    }

    /**
     * @param list<string> $tables
     */
    public function export(array $tables, string $format): Response
    {
        $names = [] === $tables ? $this->allTableNames() : $this->resolveExistingTables($tables);
        $stamp = (new \DateTimeImmutable())->format('Y-m-d-H-i-s');
        $format = strtolower(trim($format));

        return match ($format) {
            'sql' => $this->exportSql($names, $stamp),
            'xlsx' => $this->exportXlsx($names, $stamp),
            default => throw new BadRequestHttpException('Format d\'export invalide. Utilisez sql ou xlsx.'),
        };
    }

    /**
     * @return array{statements: int, executed: int}
     */
    public function importSql(UploadedFile $file): array
    {
        $original = strtolower((string) $file->getClientOriginalName());
        if (!str_ends_with($original, '.sql')) {
            throw new BadRequestHttpException('Seuls les fichiers .sql sont acceptés.');
        }
        if ($file->getSize() > 32 * 1024 * 1024) {
            throw new BadRequestHttpException('Le fichier dépasse 32 Mo.');
        }

        $sql = file_get_contents($file->getPathname());
        if (false === $sql || '' === trim($sql)) {
            throw new BadRequestHttpException('Fichier SQL vide.');
        }

        $upper = strtoupper($sql);
        foreach (self::BLOCKED_SQL as $blocked) {
            if (str_contains($upper, $blocked)) {
                throw new ConflictException(sprintf('Instruction interdite dans l\'import : %s.', trim($blocked)));
            }
        }

        $statements = $this->splitStatements($sql);
        $executed = 0;
        $skipped = 0;
        $this->connection->executeStatement('SET FOREIGN_KEY_CHECKS = 0');
        try {
            foreach ($statements as $statement) {
                if ($this->mentionsProtectedTable($statement)) {
                    ++$skipped;
                    continue;
                }
                $this->assertSafeStatement($statement);
                $this->connection->executeStatement($statement);
                ++$executed;
            }
        } finally {
            $this->connection->executeStatement('SET FOREIGN_KEY_CHECKS = 1');
        }

        return [
            'statements' => count($statements),
            'executed' => $executed,
            'skipped' => $skipped,
        ];
    }

    /**
     * @param list<string> $tables
     */
    private function exportSql(array $tables, string $stamp): StreamedResponse
    {
        $filename = sprintf('chu-ukv-%s.sql', $stamp);
        $connection = $this->connection;
        $quote = fn (string $name): string => $this->quoteName($name);

        $response = new StreamedResponse(function () use ($tables, $connection, $quote, $stamp): void {
            $out = fopen('php://output', 'w');
            if (false === $out) {
                return;
            }
            fwrite($out, "-- CHU UKV SQL export\n");
            fwrite($out, '-- Database: ' . $this->databaseName() . "\n");
            fwrite($out, '-- Generated at: ' . $stamp . "\n\n");
            fwrite($out, "SET SQL_MODE = \"NO_AUTO_VALUE_ON_ZERO\";\nSET FOREIGN_KEY_CHECKS = 0;\nSET NAMES utf8mb4;\n\n");

            foreach ($tables as $table) {
                $quoted = $quote($table);
                $create = $connection->fetchAssociative('SHOW CREATE TABLE ' . $quoted);
                $ddl = $create['Create Table'] ?? $create['Create View'] ?? null;
                fwrite($out, "-- Table `$table`\n");
                fwrite($out, "DROP TABLE IF EXISTS $quoted;\n");
                if (is_string($ddl)) {
                    fwrite($out, $ddl . ";\n\n");
                }
                foreach ($connection->iterateAssociative('SELECT * FROM ' . $quoted) as $row) {
                    $columns = array_map(fn (string $col): string => $this->quoteName($col), array_keys($row));
                    $values = array_map(fn (mixed $value): string => $this->sqlValue($value), array_values($row));
                    fwrite($out, 'INSERT INTO ' . $quoted . ' (' . implode(', ', $columns) . ') VALUES (' . implode(', ', $values) . ");\n");
                }
                fwrite($out, "\n");
            }

            fwrite($out, "SET FOREIGN_KEY_CHECKS = 1;\n");
            fclose($out);
        });

        $response->headers->set('Content-Type', 'application/sql; charset=utf-8');
        $response->headers->set(
            'Content-Disposition',
            HeaderUtils::makeDisposition(HeaderUtils::DISPOSITION_ATTACHMENT, $filename),
        );

        return $response;
    }

    /**
     * @param list<string> $tables
     */
    private function exportXlsx(array $tables, string $stamp): Response
    {
        $spreadsheet = new Spreadsheet();
        $usedNames = [];
        $first = true;

        foreach ($tables as $table) {
            $sheet = $first ? $spreadsheet->getActiveSheet() : $spreadsheet->createSheet();
            $first = false;
            $sheetName = $this->uniqueSheetName($table, $usedNames);
            $usedNames[] = $sheetName;
            $sheet->setTitle($sheetName);

            $rows = $this->connection->fetchAllAssociative(
                'SELECT * FROM ' . $this->quoteName($table) . ' LIMIT ' . (self::EXCEL_MAX_ROWS + 1),
            );
            if ([] === $rows) {
                $sheet->setCellValue('A1', '(table vide)');
                continue;
            }

            $headers = array_keys($rows[0]);
            foreach ($headers as $col => $header) {
                $sheet->setCellValue(Coordinate::stringFromColumnIndex($col + 1) . '1', $header);
            }

            $truncated = count($rows) > self::EXCEL_MAX_ROWS;
            $dataRows = $truncated ? array_slice($rows, 0, self::EXCEL_MAX_ROWS) : $rows;
            foreach ($dataRows as $rowIndex => $row) {
                $excelRow = $rowIndex + 2;
                $col = 1;
                foreach ($headers as $header) {
                    $value = $row[$header];
                    if ($value instanceof \DateTimeInterface) {
                        $value = $value->format('Y-m-d H:i:s');
                    } elseif (is_bool($value)) {
                        $value = $value ? '1' : '0';
                    } elseif (is_array($value) || is_object($value)) {
                        $value = json_encode($value, JSON_UNESCAPED_UNICODE);
                    }
                    $sheet->setCellValue(Coordinate::stringFromColumnIndex($col) . $excelRow, $value);
                    ++$col;
                }
            }
            if ($truncated) {
                $sheet->setCellValue('A' . (self::EXCEL_MAX_ROWS + 3), sprintf(
                    'Export limité à %d lignes. Utilisez le format SQL pour l\'intégralité.',
                    self::EXCEL_MAX_ROWS,
                ));
            }
        }

        $tmp = tempnam(sys_get_temp_dir(), 'chu-xlsx-');
        if (false === $tmp) {
            throw new ConflictException('Impossible de créer le fichier Excel.');
        }
        (new Xlsx($spreadsheet))->save($tmp);
        $content = file_get_contents($tmp);
        @unlink($tmp);
        $spreadsheet->disconnectWorksheets();

        $response = new Response($content === false ? '' : $content);
        $response->headers->set('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        $response->headers->set(
            'Content-Disposition',
            HeaderUtils::makeDisposition(HeaderUtils::DISPOSITION_ATTACHMENT, sprintf('chu-ukv-%s.xlsx', $stamp)),
        );

        return $response;
    }

    /**
     * @param list<string> $tables
     * @return list<string>
     */
    private function resolveExistingTables(array $tables): array
    {
        $requested = [];
        foreach ($tables as $table) {
            $name = trim((string) $table);
            if ('' === $name) {
                continue;
            }
            if (!preg_match('/^[A-Za-z0-9_]+$/', $name)) {
                throw new BadRequestHttpException(sprintf('Nom de table invalide : %s', $name));
            }
            $requested[] = $name;
        }
        $requested = array_values(array_unique($requested));
        if ([] === $requested) {
            throw new BadRequestHttpException('Sélectionnez au moins une table.');
        }

        $existing = $this->allTableNames();
        $unknown = array_values(array_diff($requested, $existing));
        if ([] !== $unknown) {
            throw new BadRequestHttpException('Table(s) inconnue(s) : ' . implode(', ', $unknown));
        }

        return $requested;
    }

    /**
     * @return list<string>
     */
    private function allTableNames(): array
    {
        $rows = $this->connection->fetchFirstColumn(
            'SELECT TABLE_NAME FROM information_schema.TABLES
             WHERE TABLE_SCHEMA = :schema AND TABLE_TYPE = \'BASE TABLE\'
             ORDER BY TABLE_NAME ASC',
            ['schema' => $this->databaseName()],
        );

        return array_map('strval', $rows);
    }

    private function databaseName(): string
    {
        return (string) $this->connection->fetchOne('SELECT DATABASE()');
    }

    private function isProtected(string $table): bool
    {
        return in_array(strtolower($table), self::PROTECTED_TABLES, true);
    }

    private function quoteName(string $name): string
    {
        return '`' . str_replace('`', '``', $name) . '`';
    }

    private function sqlValue(mixed $value): string
    {
        if (null === $value) {
            return 'NULL';
        }
        if ($value instanceof \DateTimeInterface) {
            return $this->connection->quote($value->format('Y-m-d H:i:s'));
        }
        if (is_bool($value)) {
            return $value ? '1' : '0';
        }
        if (is_int($value) || is_float($value)) {
            return (string) $value;
        }
        if (is_resource($value)) {
            return $this->connection->quote(stream_get_contents($value) ?: '');
        }

        return $this->connection->quote((string) $value);
    }

    /**
     * @return list<string>
     */
    private function splitStatements(string $sql): array
    {
        $sql = preg_replace('/^\s*--.*$/m', '', $sql) ?? $sql;
        $statements = [];
        $buffer = '';
        $quote = null;
        $length = strlen($sql);

        for ($i = 0; $i < $length; ++$i) {
            $char = $sql[$i];
            if (null !== $quote) {
                $buffer .= $char;
                if ('\\' === $char && $i + 1 < $length) {
                    $buffer .= $sql[++$i];
                    continue;
                }
                if ($char === $quote) {
                    $quote = null;
                }
                continue;
            }
            if ("'" === $char || '"' === $char || '`' === $char) {
                $quote = $char;
                $buffer .= $char;
                continue;
            }
            if (';' === $char) {
                $trimmed = trim($buffer);
                if ('' !== $trimmed) {
                    $statements[] = $trimmed;
                }
                $buffer = '';
                continue;
            }
            $buffer .= $char;
        }

        $trimmed = trim($buffer);
        if ('' !== $trimmed) {
            $statements[] = $trimmed;
        }

        return array_values(array_filter(
            $statements,
            static fn (string $statement): bool => !preg_match('/^(SET\s+NAMES|SET\s+SQL_MODE)/i', $statement),
        ));
    }

    private function assertSafeStatement(string $statement): void
    {
        $upper = strtoupper($statement);
        foreach (self::BLOCKED_SQL as $blocked) {
            if (str_contains($upper, $blocked)) {
                throw new ConflictException(sprintf('Instruction interdite : %s.', trim($blocked)));
            }
        }
    }

    private function mentionsProtectedTable(string $statement): bool
    {
        foreach (self::PROTECTED_TABLES as $protected) {
            if (preg_match('/\b' . preg_quote($protected, '/') . '\b/i', $statement)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param list<string> $used
     */
    private function uniqueSheetName(string $table, array $used): string
    {
        $base = substr($table, 0, 31);
        $name = $base;
        $i = 2;
        while (in_array($name, $used, true)) {
            $suffix = '_' . $i;
            $name = substr($base, 0, 31 - strlen($suffix)) . $suffix;
            ++$i;
        }

        return $name;
    }

    private function formatBytes(int $bytes): string
    {
        if ($bytes < 1024) {
            return $bytes . ' o';
        }
        $units = ['Ko', 'Mo', 'Go', 'To'];
        $value = $bytes / 1024;
        foreach ($units as $unit) {
            if ($value < 1024) {
                return number_format($value, $value >= 10 ? 0 : 1, ',', ' ') . ' ' . $unit;
            }
            $value /= 1024;
        }

        return number_format($value, 1, ',', ' ') . ' Po';
    }
}
