<?php

namespace rewish\craft\mcp\tools;

use Composer\Autoload\ClassLoader;
use Mcp\Capability\Attribute\McpTool;
use Mcp\Schema\ToolAnnotations;
use rewish\craft\mcp\attributes\ToolMeta;
use rewish\craft\mcp\Mcp;
use rewish\craft\mcp\services\Activity;
use rewish\craft\mcp\services\Catalog;

final class SelfTools
{
    #[McpTool(name: 'get_mcp_info', description: 'Plugin version, whether the server is enabled, and how many tools are exposed.', annotations: new ToolAnnotations(readOnlyHint: true, idempotentHint: true))]
    #[ToolMeta(category: 'mcp')]
    public function getMcpInfo(): array
    {
        return Activity::run('get_mcp_info', static function () {
            $plugin = Mcp::getInstance();
            $settings = Mcp::settings();
            $catalog = new Catalog();

            return [
                'name' => 'MCP - All in one',
                'version' => $plugin?->getVersion() ?? '1.0.0',
                'enabled' => $settings->enabled,
                'environment' => \Craft::$app->env,
                'dangerousTools' => $settings->enableDangerousTools,
                'writeMode' => $settings->entryWriteMode,
                'http' => $settings->httpTransport,
                'exposedTools' => \count($catalog->exposed()),
            ];
        });
    }

    #[McpTool(name: 'list_mcp_tools', description: 'List every tool this plugin ships, including ones hidden by the current scope or settings.', annotations: new ToolAnnotations(readOnlyHint: true, idempotentHint: true))]
    #[ToolMeta(category: 'mcp')]
    public function listMcpTools(): array
    {
        return Activity::run('list_mcp_tools', static function () {
            $tools = (new Catalog())->all();

            return ['count' => \count($tools), 'tools' => $tools];
        });
    }

    #[McpTool(name: 'reload_mcp', description: 'Reload installed Craft plugins in this process. New MCP tool classes show up on the next HTTP request. Restart bin/mcp-server to refresh a stdio session.', annotations: new ToolAnnotations(destructiveHint: false, idempotentHint: true))]
    #[ToolMeta(category: 'mcp', minScope: 'full')]
    public function reloadMcp(): array
    {
        return Activity::run('reload_mcp', function () {
            $classmap = $this->refreshComposerClassmap();
            \Craft::$app->getPlugins()->loadPlugins();

            return [
                'success' => true,
                'plugins' => \count(\Craft::$app->getPlugins()->getAllPluginInfo()),
                'classmapReloaded' => $classmap,
                'note' => 'Plugins were reloaded in this process. HTTP sees new tools on the next request. A stdio process keeps the tool list from startup, so restart bin/mcp-server after adding tools.',
            ];
        });
    }

    private function refreshComposerClassmap(): bool
    {
        $path = \Craft::getAlias('@vendor/composer/autoload_classmap.php');
        if (!is_file($path)) {
            return false;
        }

        if (\function_exists('opcache_invalidate')) {
            opcache_invalidate($path, true);
        }

        $map = include $path;
        if (!\is_array($map)) {
            return false;
        }

        foreach (spl_autoload_functions() ?: [] as $loader) {
            if (\is_array($loader) && ($loader[0] ?? null) instanceof ClassLoader) {
                $loader[0]->addClassMap($map);

                return true;
            }
        }

        return false;
    }
}
