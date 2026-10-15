<?php

namespace rewish\craft\mcp\installer\clients;

/**
 * Codex CLI / IDE extension. Writes `.codex/config.toml` for trusted projects.
 *
 * Needs an absolute `cwd`: Codex starts servers from wherever it was launched,
 * and the binary path in `args` is relative to the project.
 */
final class Codex extends BaseClient
{
    public function getId(): string
    {
        return 'codex';
    }

    public function getName(): string
    {
        return 'Codex';
    }

    public function getDescription(): string
    {
        return '.codex/config.toml, trusted projects';
    }

    public function getConfigPath(): string
    {
        return $this->configRoot.'/.codex/config.toml';
    }

    public function requiresAbsolutePaths(): bool
    {
        return true;
    }

    public function format(): string
    {
        return 'toml';
    }
}
