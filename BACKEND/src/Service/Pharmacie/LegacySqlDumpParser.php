<?php

namespace App\Service\Pharmacie;

final class LegacySqlDumpParser
{
    /**
     * @return list<array<string, mixed>>
     */
    public function parseTable(string $sql, string $table): array
    {
        $pattern = '/INSERT INTO `' . preg_quote($table, '/') . '` \(([^)]+)\) VALUES\s+(.*?);/s';
        if (!preg_match_all($pattern, $sql, $matches, PREG_SET_ORDER)) {
            return [];
        }

        $rows = [];
        foreach ($matches as $match) {
            $columns = array_map(
                static fn (string $column): string => trim($column, " \t\n\r`"),
                explode(',', $match[1]),
            );
            foreach ($this->parseValueGroups($match[2]) as $values) {
                if (count($values) !== count($columns)) {
                    continue;
                }
                $rows[] = array_combine($columns, $values);
            }
        }

        return $rows;
    }

    /**
     * @return list<list<mixed>>
     */
    private function parseValueGroups(string $block): array
    {
        $groups = [];
        $current = [];
        $token = '';
        $quoted = false;
        $inGroup = false;
        $length = strlen($block);

        for ($i = 0; $i < $length; ++$i) {
            $char = $block[$i];
            if (!$inGroup) {
                if ('(' === $char) {
                    $inGroup = true;
                    $current = [];
                    $token = '';
                }
                continue;
            }

            if ($quoted) {
                if ('\\' === $char && $i + 1 < $length) {
                    $token .= $block[$i + 1];
                    ++$i;
                    continue;
                }
                if ("'" === $char) {
                    if ($i + 1 < $length && "'" === $block[$i + 1]) {
                        $token .= "'";
                        ++$i;
                    } else {
                        $quoted = false;
                    }
                } else {
                    $token .= $char;
                }
                continue;
            }

            if ("'" === $char) {
                $quoted = true;
                continue;
            }
            if (',' === $char) {
                $current[] = $this->castToken($token);
                $token = '';
                continue;
            }
            if (')' === $char) {
                $current[] = $this->castToken($token);
                $groups[] = $current;
                $inGroup = false;
                $token = '';
                continue;
            }
            $token .= $char;
        }

        return $groups;
    }

    private function castToken(string $token): mixed
    {
        $value = trim($token);
        if ('NULL' === strtoupper($value) || '' === $value) {
            return null;
        }
        if (is_numeric($value)) {
            return str_contains($value, '.') ? $value : (int) $value;
        }

        return $value;
    }
}
