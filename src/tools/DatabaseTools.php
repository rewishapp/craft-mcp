<?php

namespace rewish\craft\mcp\tools;

use craft\db\Table;
use Mcp\Capability\Attribute\McpTool;
use Mcp\Exception\ToolCallException;
use Mcp\Schema\ToolAnnotations;
use rewish\craft\mcp\attributes\ToolMeta;
use rewish\craft\mcp\services\Activity;
use rewish\craft\mcp\support\SqlGuard;

final class DatabaseTools
{
    #[McpTool(name: 'get_database_info', description: 'Database driver and server version. Credentials are not included.', annotations: new ToolAnnotations(readOnlyHint: true, idempotentHint: true))]
    #[ToolMeta(category: 'database', minScope: 'full')]
    public function getDatabaseInfo(): array
    {
        return Activity::run('get_database_info', static function () {
            $db = \Craft::$app->getDb();
            $version = null;

            try {
                $version = $db->getMasterPdo()?->getAttribute(\PDO::ATTR_SERVER_VERSION);
            } catch (\Throwable) {
                $version = null;
            }

            return [
                'driver' => $db->getDriverName(),
                'serverVersion' => $version,
            ];
        });
    }

    #[McpTool(name: 'get_database_schema', description: 'List tables, or columns for one table when table is passed.', annotations: new ToolAnnotations(readOnlyHint: true, idempotentHint: true))]
    #[ToolMeta(category: 'database', minScope: 'full')]
    public function getDatabaseSchema(?string $table = null): array
    {
        return Activity::run('get_database_schema', static function () use ($table) {
            $schema = \Craft::$app->getDb()->getSchema();
            if ($table === null || $table === '') {
                $names = $schema->getTableNames();
                sort($names);

                return ['count' => \count($names), 'tables' => $names];
            }

            $tableSchema = $schema->getTableSchema($table);
            if ($tableSchema === null) {
                throw new ToolCallException("Unknown table {$table}.");
            }

            $columns = [];
            foreach ($tableSchema->columns as $column) {
                $columns[] = [
                    'name' => $column->name,
                    'type' => $column->type,
                    'allowNull' => $column->allowNull,
                    'primaryKey' => (bool) $column->isPrimaryKey,
                ];
            }

            return ['table' => $tableSchema->name, 'columns' => $columns];
        });
    }

    #[McpTool(name: 'get_table_counts', description: 'Row counts for the core Craft tables.', annotations: new ToolAnnotations(readOnlyHint: true, idempotentHint: true))]
    #[ToolMeta(category: 'database', minScope: 'full')]
    public function getTableCounts(): array
    {
        return Activity::run('get_table_counts', function () {
            $db = \Craft::$app->getDb();
            $schema = $db->getSchema();
            $counts = [];
            foreach ([Table::ELEMENTS, Table::ENTRIES, Table::USERS, Table::ASSETS, Table::CATEGORIES, Table::TAGS] as $token) {
                $name = $schema->getRawTableName($token);
                if ($schema->getTableSchema($name) === null) {
                    continue;
                }
                $counts[$name] = (int) $db->createCommand("SELECT COUNT(*) FROM {{%{$this->bare($token)}}}")->queryScalar();
            }

            return ['counts' => $counts];
        });
    }

    #[McpTool(name: 'run_query', description: 'Run one read-only SELECT. Results are capped at 200 rows.', annotations: new ToolAnnotations(readOnlyHint: true))]
    #[ToolMeta(category: 'database', minScope: 'full', dangerous: true)]
    public function runQuery(string $sql, int $limit = 50): array
    {
        return Activity::run('run_query', static function () use ($sql, $limit) {
            try {
                $safe = SqlGuard::select($sql, $limit);
            } catch (\Throwable $e) {
                throw new ToolCallException($e->getMessage());
            }

            $rows = \Craft::$app->getDb()->createCommand($safe)->queryAll();

            return ['count' => \count($rows), 'rows' => $rows];
        });
    }

    private function bare(string $token): string
    {
        return trim($token, '{}%');
    }
}
