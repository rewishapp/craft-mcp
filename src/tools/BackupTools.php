<?php

namespace rewish\craft\mcp\tools;

use Mcp\Capability\Attribute\McpTool;
use Mcp\Schema\ToolAnnotations;
use rewish\craft\mcp\attributes\ToolMeta;
use rewish\craft\mcp\services\Activity;

final class BackupTools
{
    #[McpTool(name: 'list_backups', description: 'List database backup files in storage/backups.', annotations: new ToolAnnotations(readOnlyHint: true))]
    #[ToolMeta(category: 'backup', minScope: 'full')]
    public function listBackups(): array
    {
        return Activity::run('list_backups', static function () {
            $dir = \Craft::$app->getPath()->getDbBackupPath();
            $files = glob($dir.'/*') ?: [];
            $rows = [];
            foreach ($files as $file) {
                if (!is_file($file)) {
                    continue;
                }
                $rows[] = [
                    'name' => basename($file),
                    'bytes' => filesize($file),
                    'modified' => date(\DATE_ATOM, (int) filemtime($file)),
                ];
            }

            return ['count' => \count($rows), 'backups' => $rows];
        });
    }

    #[McpTool(name: 'create_backup', description: 'Create a database backup in storage/backups.', annotations: new ToolAnnotations(destructiveHint: true))]
    #[ToolMeta(category: 'backup', minScope: 'full', dangerous: true)]
    public function createBackup(): array
    {
        return Activity::run('create_backup', static function () {
            $path = \Craft::$app->getDb()->backup();

            return ['success' => true, 'file' => basename($path)];
        });
    }
}
