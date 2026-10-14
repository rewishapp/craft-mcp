<?php

namespace rewish\craft\mcp\tools;

use Mcp\Capability\Attribute\McpTool;
use Mcp\Exception\ToolCallException;
use Mcp\Schema\ToolAnnotations;
use rewish\craft\mcp\attributes\ToolMeta;
use rewish\craft\mcp\services\Activity;
use rewish\craft\mcp\support\SqlGuard;

final class DebugTools
{
    #[McpTool(name: 'get_queue_jobs', description: 'List queue jobs waiting, reserved, or failed.', annotations: new ToolAnnotations(readOnlyHint: true))]
    #[ToolMeta(category: 'debug')]
    public function getQueueJobs(int $limit = 25): array
    {
        return Activity::run('get_queue_jobs', static function () use ($limit) {
            $queue = \Craft::$app->getQueue();
            $jobs = method_exists($queue, 'getJobInfo') ? $queue->getJobInfo(max(1, min($limit, 100))) : [];

            return [
                'total' => method_exists($queue, 'getTotalJobs') ? $queue->getTotalJobs() : \count($jobs),
                'count' => \count($jobs),
                'jobs' => $jobs,
            ];
        });
    }

    #[McpTool(name: 'get_project_config_diff', description: 'Summarize project config changes that are not applied yet.', annotations: new ToolAnnotations(readOnlyHint: true))]
    #[ToolMeta(category: 'debug', minScope: 'full')]
    public function getProjectConfigDiff(): array
    {
        return Activity::run('get_project_config_diff', static function () {
            $projectConfig = \Craft::$app->getProjectConfig();

            return [
                'pending' => $projectConfig->getPendingChangeSummary(),
                'externalExists' => $projectConfig->getDoesExternalConfigExist(),
            ];
        });
    }

    #[McpTool(name: 'get_deprecations', description: 'List recent deprecation warnings.', annotations: new ToolAnnotations(readOnlyHint: true))]
    #[ToolMeta(category: 'debug')]
    public function getDeprecations(int $limit = 20): array
    {
        return Activity::run('get_deprecations', static function () use ($limit) {
            $logs = \Craft::$app->getDeprecator()->getLogs(max(1, min($limit, 100)));
            $rows = [];
            foreach ($logs as $log) {
                $rows[] = [
                    'key' => $log->key ?? null,
                    'message' => $log->message ?? null,
                    'file' => $log->file ?? null,
                    'line' => $log->line ?? null,
                    'lastOccurrence' => isset($log->lastOccurrence) ? (string) $log->lastOccurrence : null,
                ];
            }

            return ['total' => \Craft::$app->getDeprecator()->getTotalLogs(), 'count' => \count($rows), 'logs' => $rows];
        });
    }

    #[McpTool(name: 'explain_query', description: 'Run EXPLAIN on a single SELECT.', annotations: new ToolAnnotations(readOnlyHint: true))]
    #[ToolMeta(category: 'debug', minScope: 'full')]
    public function explainQuery(string $sql): array
    {
        return Activity::run('explain_query', static function () use ($sql) {
            try {
                $safe = SqlGuard::select($sql, 1);
            } catch (\Throwable $e) {
                throw new ToolCallException($e->getMessage());
            }

            $rows = \Craft::$app->getDb()->createCommand('EXPLAIN '.$safe)->queryAll();

            return ['count' => \count($rows), 'plan' => $rows];
        });
    }

    #[McpTool(name: 'get_environment', description: 'PHP and OS environment details. Secret-looking values are redacted.', annotations: new ToolAnnotations(readOnlyHint: true, idempotentHint: true))]
    #[ToolMeta(category: 'debug', minScope: 'full')]
    public function getEnvironment(): array
    {
        return Activity::run('get_environment', static fn () => [
            'php' => \PHP_VERSION,
            'sapi' => \PHP_SAPI,
            'os' => \PHP_OS_FAMILY,
            'memoryLimit' => \ini_get('memory_limit'),
            'maxExecutionTime' => \ini_get('max_execution_time'),
            'timezone' => date_default_timezone_get(),
            'craftEnvironment' => \Craft::$app->env,
        ]);
    }

    #[McpTool(name: 'list_event_handlers', description: 'List Yii event names registered on the Craft app and its main services, with listener counts.', annotations: new ToolAnnotations(readOnlyHint: true))]
    #[ToolMeta(category: 'debug', minScope: 'full')]
    public function listEventHandlers(): array
    {
        return Activity::run('list_event_handlers', function () {
            $targets = [
                'app' => \Craft::$app,
                'elements' => \Craft::$app->getElements(),
                'entries' => \Craft::$app->getEntries(),
                'plugins' => \Craft::$app->getPlugins(),
                'projectConfig' => \Craft::$app->getProjectConfig(),
            ];

            $report = [];
            foreach ($targets as $name => $component) {
                $report[$name] = $this->events($component);
            }

            return ['components' => $report];
        });
    }

    /**
     * @return list<array{event: string, listeners: int}>
     */
    private function events(object $component): array
    {
        try {
            $ref = new \ReflectionClass($component);
            while (!$ref->hasProperty('_events') && $ref->getParentClass()) {
                $parent = $ref->getParentClass();
                if (!$parent instanceof \ReflectionClass) {
                    break;
                }
                $ref = $parent;
            }
            if (!$ref->hasProperty('_events')) {
                return [];
            }
            $property = $ref->getProperty('_events');
            $property->setAccessible(true);
            $events = $property->getValue($component);
        } catch (\ReflectionException) {
            return [];
        }

        if (!\is_array($events)) {
            return [];
        }

        $rows = [];
        foreach ($events as $name => $listeners) {
            $rows[] = ['event' => (string) $name, 'listeners' => \is_array($listeners) ? \count($listeners) : 0];
        }

        return $rows;
    }
}
