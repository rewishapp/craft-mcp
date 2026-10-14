<?php

namespace rewish\craft\mcp\support;

final class SqlGuard
{
    public static function select(string $sql, int $limit): string
    {
        $sql = trim($sql);
        $sql = rtrim($sql, "; \t\n\r");

        if ($sql === '' || str_contains($sql, ';')) {
            throw new \InvalidArgumentException('Only a single SELECT statement is allowed.');
        }

        if (!preg_match('/^(select|with)\b/i', $sql)) {
            throw new \InvalidArgumentException('Only SELECT queries are allowed.');
        }

        if (preg_match('/\b(insert|update|delete|drop|alter|create|grant|revoke|truncate|replace|attach|detach|pragma|load_file|into\s+outfile|into\s+dumpfile)\b/i', $sql)) {
            throw new \InvalidArgumentException('That statement is not allowed.');
        }

        $limit = max(1, min($limit, 200));
        if (!preg_match('/\blimit\b/i', $sql)) {
            $sql .= ' LIMIT '.$limit;
        }

        return $sql;
    }
}
