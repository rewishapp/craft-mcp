<?php

namespace rewish\craft\mcp\installer;

final class EnvironmentDetector
{
    public const DDEV = 'ddev';

    public const NATIVE = 'native';

    /** @var list<string> */
    private const DDEV_BINARY_PATHS = [
        '/usr/local/bin/ddev',
        '/opt/homebrew/bin/ddev',
        '/home/linuxbrew/.linuxbrew/bin/ddev',
    ];

    public function __construct(private readonly string $projectRoot) {}

    public function detect(): string
    {
        return $this->isDdevEnvironment() ? self::DDEV : self::NATIVE;
    }

    public function isDdevEnvironment(): bool
    {
        if (getenv('DDEV_PROJECT') !== false) {
            return true;
        }

        return is_dir($this->projectRoot.'/.ddev');
    }

    public function getDdevBinaryPath(): ?string
    {
        foreach (self::DDEV_BINARY_PATHS as $path) {
            if (is_file($path) && is_executable($path)) {
                return $path;
            }
        }

        return $this->which('ddev');
    }

    public function getPhpBinaryPath(): ?string
    {
        if (\defined('PHP_BINARY') && is_file(\PHP_BINARY)) {
            return \PHP_BINARY;
        }

        return $this->which('php');
    }

    private function which(string $binary): ?string
    {
        $command = \PHP_OS_FAMILY === 'Windows' ? "where {$binary} 2>NUL" : "which {$binary} 2>/dev/null";
        $output = [];
        $code = 0;
        exec($command, $output, $code);
        if ($code === 0 && isset($output[0]) && $output[0] !== '') {
            return trim($output[0]);
        }

        return null;
    }
}
