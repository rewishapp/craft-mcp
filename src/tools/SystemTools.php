<?php

namespace rewish\craft\mcp\tools;

use Mcp\Capability\Attribute\McpTool;
use Mcp\Schema\ToolAnnotations;
use rewish\craft\mcp\attributes\ToolMeta;
use rewish\craft\mcp\services\Activity;
use rewish\craft\mcp\support\ConsoleCommands;

final class SystemTools
{
    #[McpTool(name: 'get_system_info', description: 'Craft version, PHP version, database driver, and environment.', annotations: new ToolAnnotations(readOnlyHint: true, idempotentHint: true))]
    #[ToolMeta(category: 'system')]
    public function getSystemInfo(): array
    {
        return Activity::run('get_system_info', static function () {
            $db = \Craft::$app->getDb();

            return [
                'craft' => \Craft::$app->getVersion(),
                'php' => \PHP_VERSION,
                'environment' => \Craft::$app->env,
                'devMode' => (bool) \Craft::$app->getConfig()->getGeneral()->devMode,
                'dbDriver' => $db->getDriverName(),
                'timezone' => \Craft::$app->getTimeZone(),
                'sites' => \count(\Craft::$app->getSites()->getAllSites()),
                'plugins' => \count(\Craft::$app->getPlugins()->getAllPluginInfo()),
            ];
        });
    }

    #[McpTool(name: 'get_config', description: 'A safe slice of general config. Secrets are not included.', annotations: new ToolAnnotations(readOnlyHint: true, idempotentHint: true))]
    #[ToolMeta(category: 'system', minScope: 'full')]
    public function getConfig(): array
    {
        return Activity::run('get_config', static function () {
            $general = \Craft::$app->getConfig()->getGeneral();

            return [
                'devMode' => (bool) $general->devMode,
                'allowAdminChanges' => (bool) $general->allowAdminChanges,
                'cpTrigger' => $general->cpTrigger,
                'timezone' => $general->timezone,
                'defaultWeekStartDay' => $general->defaultWeekStartDay,
                'maxUploadFileSize' => $general->maxUploadFileSize,
                'runQueueAutomatically' => (bool) $general->runQueueAutomatically,
            ];
        });
    }

    #[McpTool(name: 'read_logs', description: 'Read the tail of the newest Craft log file. Lines that look like secrets are redacted.', annotations: new ToolAnnotations(readOnlyHint: true))]
    #[ToolMeta(category: 'system', minScope: 'full')]
    public function readLogs(int $lines = 80, ?string $contains = null): array
    {
        return Activity::run('read_logs', function () use ($lines, $contains) {
            $path = $this->latestLog();
            if ($path === null) {
                return ['found' => false, 'lines' => []];
            }

            $rows = $this->tail($path, max(1, min($lines, 300)));
            if ($contains !== null && $contains !== '') {
                $rows = array_values(array_filter($rows, static fn (string $line) => str_contains($line, $contains)));
            }

            return [
                'found' => true,
                'file' => basename($path),
                'count' => \count($rows),
                'lines' => array_map($this->redact(...), $rows),
            ];
        });
    }

    #[McpTool(name: 'get_last_error', description: 'Return the most recent error line from the Craft log.', annotations: new ToolAnnotations(readOnlyHint: true))]
    #[ToolMeta(category: 'system', minScope: 'full')]
    public function getLastError(): array
    {
        return Activity::run('get_last_error', function () {
            $path = $this->latestLog();
            if ($path === null) {
                return ['found' => false];
            }

            $match = null;
            foreach ($this->tail($path, 400) as $line) {
                if (preg_match('/\b(error|critical|emergency)\b/i', $line)) {
                    $match = $line;
                }
            }

            return ['found' => $match !== null, 'line' => $match ? $this->redact($match) : null];
        });
    }

    #[McpTool(name: 'clear_caches', description: 'Flush Craft data and element caches.', annotations: new ToolAnnotations(destructiveHint: true))]
    #[ToolMeta(category: 'system', minScope: 'full', dangerous: true)]
    public function clearCaches(): array
    {
        return Activity::run('clear_caches', static function () {
            \Craft::$app->getCache()->flush();
            \Craft::$app->getElements()->invalidateAllCaches();

            return ['success' => true];
        });
    }

    #[McpTool(name: 'list_console_commands', description: 'List Craft console command ids. Complete when the server is running over stdio. Unavailable over HTTP.', annotations: new ToolAnnotations(readOnlyHint: true, idempotentHint: true))]
    #[ToolMeta(category: 'system')]
    public function listConsoleCommands(): array
    {
        return Activity::run('list_console_commands', static function () {
            // Over HTTP Craft loads web controllers, so a count of 0 would read as an install with none.
            if (!\Craft::$app->getRequest()->getIsConsoleRequest()) {
                return [
                    'count' => null,
                    'commands' => null,
                    'skippedModules' => [],
                    'unavailable' => 'Console commands can only be listed over stdio. In a web request Craft loads web controllers in place of console ones, so this transport cannot see them. Run `php craft help/list` on the server, or connect over stdio.',
                ];
            }

            $help = new ConsoleCommands('help', \Craft::$app);
            $commands = array_values($help->getCommands());

            return [
                'count' => \count($commands),
                'commands' => $commands,
                'skippedModules' => $help->skipped(),
                'unavailable' => null,
            ];
        });
    }

    #[McpTool(name: 'list_routes', description: 'List custom routes saved in Craft.', annotations: new ToolAnnotations(readOnlyHint: true, idempotentHint: true))]
    #[ToolMeta(category: 'system')]
    public function listRoutes(): array
    {
        return Activity::run('list_routes', static function () {
            $routes = [];
            foreach (\Craft::$app->getRoutes()->getProjectConfigRoutes() as $route) {
                $routes[] = [
                    'uri' => $route['pattern'] ?? $route['uriPattern'] ?? null,
                    'template' => $route['template'] ?? null,
                ];
            }

            return ['count' => \count($routes), 'routes' => $routes];
        });
    }

    private function latestLog(): ?string
    {
        $dir = \Craft::$app->getPath()->getLogPath();
        $files = glob($dir.'/*.log') ?: [];
        if ($files === []) {
            return null;
        }
        usort($files, static fn (string $a, string $b) => filemtime($b) <=> filemtime($a));

        return $files[0];
    }

    /**
     * @return list<string>
     */
    private function tail(string $path, int $lines): array
    {
        $content = @file($path, \FILE_IGNORE_NEW_LINES);
        if (!\is_array($content)) {
            return [];
        }

        return \array_slice($content, -$lines);
    }

    private function redact(string $line): string
    {
        return (string) preg_replace('/((?:secret|password|token|key|authorization)\s*[=:]\s*)(\S+)/i', '$1[redacted]', $line);
    }
}
