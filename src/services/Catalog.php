<?php

namespace rewish\craft\mcp\services;

use Mcp\Capability\Attribute\McpTool;
use rewish\craft\mcp\attributes\ToolMeta;
use rewish\craft\mcp\Mcp;
use rewish\craft\mcp\policy\Gate;

final class Catalog
{
    /**
     * @return list<array<string, mixed>>
     */
    public function all(): array
    {
        $settings = Mcp::settings();
        $gate = new Gate($settings->enableDangerousTools, $settings->disabledTools, null);
        $rows = [];

        foreach (glob(\dirname(__DIR__).'/tools/*.php') ?: [] as $file) {
            $class = 'rewish\craft\mcp\tools\\'.basename($file, '.php');
            if (!class_exists($class)) {
                continue;
            }
            $available = !method_exists($class, 'isAvailable') || (bool) $class::isAvailable();
            foreach ((new \ReflectionClass($class))->getMethods(\ReflectionMethod::IS_PUBLIC) as $method) {
                if ($method->getDeclaringClass()->getName() !== $class || $method->isStatic()) {
                    continue;
                }
                $tool = $method->getAttributes(McpTool::class)[0] ?? null;
                $metaAttr = $method->getAttributes(ToolMeta::class)[0] ?? null;
                if ($tool === null || $metaAttr === null) {
                    continue;
                }
                $args = $tool->getArguments();
                $meta = $metaAttr->newInstance();
                if (!$meta instanceof ToolMeta) {
                    continue;
                }
                $name = (string) ($args['name'] ?? $method->getName());
                $rows[] = [
                    'name' => $name,
                    'description' => (string) ($args['description'] ?? ''),
                    'category' => $meta->category,
                    'minScope' => $meta->minScope,
                    'dangerous' => $meta->dangerous,
                    'available' => $available,
                    'exposed' => $available && $gate->allows($name, $meta),
                ];
            }
        }

        usort($rows, static fn (array $a, array $b) => [$a['category'], $a['name']] <=> [$b['category'], $b['name']]);

        return $rows;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function exposed(): array
    {
        return array_values(array_filter($this->all(), static fn (array $tool) => $tool['exposed']));
    }
}
