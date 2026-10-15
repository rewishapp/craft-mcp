<?php

namespace rewish\craft\mcp\installer;

use craft\helpers\FileHelper;
use craft\helpers\Json;

final class JsonConfigWriter
{
    private const JSON_FLAGS = \JSON_PRETTY_PRINT | \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE;

    /**
     * @param array<string, mixed> $serverConfig
     */
    public function write(string $path, string $serverName, array $serverConfig): WriteResult
    {
        $existing = $this->load($path);
        $serverExisted = $existing !== null && isset($existing['mcpServers'][$serverName]);
        $config = $existing ?? [];
        $config['mcpServers'] ??= [];
        $config['mcpServers'][$serverName] = $serverConfig;
        $this->save($path, $config);

        if ($existing === null) {
            return WriteResult::created($path);
        }

        return $serverExisted ? WriteResult::overwritten($path) : WriteResult::added($path);
    }

    public function serverExists(string $path, string $serverName): bool
    {
        $existing = $this->load($path);

        return $existing !== null && isset($existing['mcpServers'][$serverName]);
    }

    /**
     * @return null|array<string, mixed>
     */
    private function load(string $path): ?array
    {
        if (!is_file($path)) {
            return null;
        }
        $content = trim((string) file_get_contents($path));
        if ($content === '') {
            return null;
        }

        return Json::decode($content);
    }

    /**
     * @param array<string, mixed> $config
     */
    private function save(string $path, array $config): void
    {
        $dir = \dirname($path);
        if (!is_dir($dir)) {
            FileHelper::createDirectory($dir);
        }
        file_put_contents($path, json_encode($config, self::JSON_FLAGS)."\n");
    }
}
