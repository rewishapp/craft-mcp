<?php

namespace rewish\craft\mcp\installer\clients;

final class Cursor extends BaseClient
{
    public function getId(): string
    {
        return 'cursor';
    }

    public function getName(): string
    {
        return 'Cursor';
    }

    public function getDescription(): string
    {
        return '.cursor/mcp.json';
    }

    public function getConfigPath(): string
    {
        return $this->configRoot.'/.cursor/mcp.json';
    }
}
