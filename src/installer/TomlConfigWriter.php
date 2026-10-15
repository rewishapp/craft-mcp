<?php

namespace rewish\craft\mcp\installer;

use craft\helpers\FileHelper;

/**
 * Writes a `[mcp_servers.<name>]` table into a Codex `config.toml`.
 *
 * Works on lines so comments and other settings survive. Owns the server’s
 * table and its subtables (`[mcp_servers.<name>.env]`), replaces them if they
 * exist, and appends them if not.
 */
final class TomlConfigWriter
{
    /**
     * @param array<string, mixed> $serverConfig
     */
    public function write(string $path, string $serverName, array $serverConfig): WriteResult
    {
        $existing = is_file($path) ? (string) file_get_contents($path) : null;
        $table = $this->table($serverName, $serverConfig);

        if ($existing === null || trim($existing) === '') {
            $this->save($path, $table);

            return WriteResult::created($path);
        }

        [$kept, $found] = $this->without($existing, $serverName);
        $this->save($path, rtrim($kept, "\n")."\n\n".$table);

        return $found ? WriteResult::overwritten($path) : WriteResult::added($path);
    }

    public function serverExists(string $path, string $serverName): bool
    {
        if (!is_file($path)) {
            return false;
        }

        foreach (explode("\n", (string) file_get_contents($path)) as $line) {
            if ($this->ownsHeader($line, $serverName)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array<string, mixed> $config
     */
    private function table(string $serverName, array $config): string
    {
        $lines = ['[mcp_servers.'.$this->key($serverName).']'];
        foreach ($config as $key => $value) {
            $lines[] = $key.' = '.$this->value($value);
        }

        return implode("\n", $lines)."\n";
    }

    /**
     * @return array{0: string, 1: bool}
     */
    private function without(string $content, string $serverName): array
    {
        $kept = [];
        $skipping = false;
        $found = false;

        foreach (explode("\n", $content) as $line) {
            if ($this->isHeader($line)) {
                $skipping = $this->ownsHeader($line, $serverName);
                $found = $found || $skipping;
            }
            if (!$skipping) {
                $kept[] = $line;
            }
        }

        return [implode("\n", $kept), $found];
    }

    private function isHeader(string $line): bool
    {
        return str_starts_with(ltrim($line), '[');
    }

    private function ownsHeader(string $line, string $serverName): bool
    {
        $names = array_unique([preg_quote($serverName, '/'), preg_quote($this->quoted($serverName), '/')]);
        $pattern = '/^\s*\[\s*mcp_servers\s*\.\s*(?:'.implode('|', $names).')\s*(?:\]|\.)/';

        return preg_match($pattern, $line) === 1;
    }

    private function key(string $name): string
    {
        return preg_match('/^[A-Za-z0-9_-]+$/', $name) === 1 ? $name : $this->quoted($name);
    }

    private function quoted(string $value): string
    {
        $escaped = strtr($value, ['\\' => '\\\\', '"' => '\\"', "\n" => '\\n', "\r" => '\\r', "\t" => '\\t']);

        return '"'.$escaped.'"';
    }

    private function value(mixed $value): string
    {
        if (\is_array($value)) {
            return '['.implode(', ', array_map(fn ($v) => $this->value($v), $value)).']';
        }

        return $this->quoted((string) $value);
    }

    private function save(string $path, string $content): void
    {
        $dir = \dirname($path);
        if (!is_dir($dir)) {
            FileHelper::createDirectory($dir);
        }
        file_put_contents($path, $content);
    }
}
