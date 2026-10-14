<?php

namespace rewish\craft\mcp\tools;

use Mcp\Capability\Attribute\McpTool;
use Mcp\Exception\ToolCallException;
use Mcp\Schema\ToolAnnotations;
use Psy\Configuration;
use Psy\Shell;
use rewish\craft\mcp\attributes\ToolMeta;
use rewish\craft\mcp\Mcp;
use rewish\craft\mcp\services\Activity;

final class TinkerTools
{
    public static function isAvailable(): bool
    {
        return Mcp::settings()->enableTinker && class_exists(Configuration::class);
    }

    #[McpTool(name: 'tinker', description: 'Execute PHP inside this Craft process. Off unless enableTinker is on and psy/psysh is installed.', annotations: new ToolAnnotations(destructiveHint: true))]
    #[ToolMeta(category: 'debug', minScope: 'full', dangerous: true)]
    public function tinker(string $code): array
    {
        return Activity::run('tinker', static function () use ($code) {
            if (!self::isAvailable()) {
                throw new ToolCallException('Tinker is off. Install psy/psysh and turn on enableTinker.');
            }

            $config = new Configuration();
            $shell = new Shell($config);
            ob_start();

            try {
                $result = $shell->execute($code);
            } finally {
                $output = ob_get_clean();
            }

            return [
                'success' => true,
                'output' => \is_string($output) ? $output : '',
                'result' => \is_scalar($result) || $result === null ? $result : get_debug_type($result),
            ];
        });
    }
}
