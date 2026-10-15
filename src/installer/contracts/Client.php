<?php

namespace rewish\craft\mcp\installer\contracts;

interface Client
{
    public function getId(): string;

    public function getName(): string;

    public function getDescription(): string;

    public function getConfigPath(): string;

    public function requiresAbsolutePaths(): bool;

    /**
     * @return array<string, mixed>
     */
    public function generateServerConfig(string $environment, ?string $projectPath = null, ?string $craftSubdirectory = null): array;

    public function format(): string;
}
