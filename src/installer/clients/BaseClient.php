<?php

namespace rewish\craft\mcp\installer\clients;

use rewish\craft\mcp\installer\contracts\Client;
use rewish\craft\mcp\installer\EnvironmentDetector;

abstract class BaseClient implements Client
{
    protected const BIN_PATH = 'vendor/bin/mcp-server';

    public function __construct(
        protected readonly string $projectRoot,
        protected readonly string $configRoot,
        protected readonly EnvironmentDetector $envDetector,
    ) {}

    public function requiresAbsolutePaths(): bool
    {
        return false;
    }

    public function format(): string
    {
        return 'json';
    }

    public function generateServerConfig(string $environment, ?string $projectPath = null, ?string $craftSubdirectory = null): array
    {
        return $environment === EnvironmentDetector::DDEV
            ? $this->buildDdevConfig($projectPath, $craftSubdirectory)
            : $this->buildNativeConfig($projectPath, $craftSubdirectory);
    }

    /**
     * @return array{command: string, args: list<string>, cwd?: string}
     */
    protected function buildDdevConfig(?string $projectPath, ?string $craftSubdirectory): array
    {
        $config = [
            'command' => $this->ddevCommand($projectPath),
            'args' => ['exec', 'php', $this->binPath($craftSubdirectory)],
        ];
        if ($projectPath !== null && $this->requiresAbsolutePaths()) {
            $config['cwd'] = $projectPath;
        }

        return $config;
    }

    /**
     * @return array{command: string, args: list<string>, cwd?: string}
     */
    protected function buildNativeConfig(?string $projectPath, ?string $craftSubdirectory): array
    {
        $config = [
            'command' => $this->phpCommand($projectPath),
            'args' => [$this->binPath($craftSubdirectory)],
        ];
        if ($projectPath !== null && $this->requiresAbsolutePaths()) {
            $config['cwd'] = $projectPath;
        }

        return $config;
    }

    private function binPath(?string $craftSubdirectory): string
    {
        return $craftSubdirectory ? $craftSubdirectory.'/'.self::BIN_PATH : self::BIN_PATH;
    }

    private function ddevCommand(?string $projectPath): string
    {
        if ($projectPath !== null && $this->requiresAbsolutePaths()) {
            return $this->envDetector->getDdevBinaryPath() ?? 'ddev';
        }

        return 'ddev';
    }

    private function phpCommand(?string $projectPath): string
    {
        if ($projectPath !== null && $this->requiresAbsolutePaths()) {
            return $this->envDetector->getPhpBinaryPath() ?? 'php';
        }

        return 'php';
    }
}
