<?php

namespace rewish\craft\mcp\installer\clients;

final class ClaudeCode extends BaseClient
{
    public function getId(): string
    {
        return 'claude-code';
    }

    public function getName(): string
    {
        return 'Claude Code';
    }

    public function getDescription(): string
    {
        return '.mcp.json in project root';
    }

    public function getConfigPath(): string
    {
        return $this->configRoot.'/.mcp.json';
    }
}
