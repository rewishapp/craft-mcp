<?php

namespace rewish\craft\mcp\services;

use Mcp\Capability\Attribute\McpPrompt;
use Mcp\Capability\Attribute\McpResource;
use Mcp\Capability\Attribute\McpResourceTemplate;
use Mcp\Capability\Attribute\McpTool;
use Mcp\Server\Builder;
use rewish\craft\mcp\attributes\ToolMeta;
use rewish\craft\mcp\events\RegisterClassesEvent;
use rewish\craft\mcp\Mcp;
use rewish\craft\mcp\policy\Gate;

final class Registrar
{
    public function apply(Builder $builder, Gate $gate): void
    {
        foreach ($this->classes('tools', Mcp::EVENT_REGISTER_TOOLS) as $class) {
            if (!$this->available($class)) {
                continue;
            }
            foreach ($this->methods($class) as $method) {
                $tool = $this->args($method, McpTool::class);
                $meta = $this->meta($method);
                if ($tool === null || $meta === null) {
                    continue;
                }
                $name = (string) ($tool['name'] ?? $method->getName());
                if (!$gate->allows($name, $meta)) {
                    continue;
                }
                $builder->addTool(
                    [$class, $method->getName()],
                    $name,
                    null,
                    isset($tool['description']) ? (string) $tool['description'] : null,
                    $tool['annotations'] ?? null,
                );
            }
        }

        $settings = Mcp::settings();
        foreach ($this->classes('prompts', Mcp::EVENT_REGISTER_PROMPTS) as $class) {
            foreach ($this->methods($class) as $method) {
                $prompt = $this->args($method, McpPrompt::class);
                if ($prompt === null) {
                    continue;
                }
                $name = (string) ($prompt['name'] ?? '');
                if ($name !== '' && \in_array($name, $settings->disabledPrompts, true)) {
                    continue;
                }
                $builder->addPrompt(
                    [$class, $method->getName()],
                    $name !== '' ? $name : null,
                    null,
                    isset($prompt['description']) ? (string) $prompt['description'] : null,
                );
            }
        }

        foreach ($this->classes('resources', Mcp::EVENT_REGISTER_RESOURCES) as $class) {
            foreach ($this->methods($class) as $method) {
                $resource = $this->args($method, McpResource::class);
                if ($resource !== null) {
                    $name = (string) ($resource['name'] ?? '');
                    if ($name !== '' && \in_array($name, $settings->disabledResources, true)) {
                        continue;
                    }
                    $builder->addResource(
                        [$class, $method->getName()],
                        (string) $resource['uri'],
                        $name !== '' ? $name : null,
                        null,
                        isset($resource['description']) ? (string) $resource['description'] : null,
                        isset($resource['mimeType']) ? (string) $resource['mimeType'] : null,
                    );

                    continue;
                }

                $template = $this->args($method, McpResourceTemplate::class);
                if ($template === null) {
                    continue;
                }
                $name = (string) ($template['name'] ?? '');
                if ($name !== '' && \in_array($name, $settings->disabledResources, true)) {
                    continue;
                }
                $builder->addResourceTemplate(
                    [$class, $method->getName()],
                    (string) $template['uriTemplate'],
                    $name !== '' ? $name : null,
                    null,
                    isset($template['description']) ? (string) $template['description'] : null,
                    isset($template['mimeType']) ? (string) $template['mimeType'] : null,
                );
            }
        }
    }

    /**
     * @return list<class-string>
     */
    private function classes(string $directory, string $eventName): array
    {
        $path = \dirname(__DIR__).'/'.$directory;
        $classes = [];
        foreach (glob($path.'/*.php') ?: [] as $file) {
            $classes[] = 'rewish\craft\mcp\\'.$directory.'\\'.basename($file, '.php');
        }

        $plugin = Mcp::getInstance();
        if ($plugin !== null && $plugin->hasEventHandlers($eventName)) {
            $event = new RegisterClassesEvent(['classes' => $classes]);
            $plugin->trigger($eventName, $event);
            $classes = $event->classes;
        }

        return $classes;
    }

    /**
     * @param class-string $class
     */
    private function available(string $class): bool
    {
        if (!method_exists($class, 'isAvailable')) {
            return true;
        }

        return (bool) $class::isAvailable();
    }

    /**
     * @param class-string $class
     *
     * @return list<\ReflectionMethod>
     */
    private function methods(string $class): array
    {
        if (!class_exists($class)) {
            return [];
        }

        $methods = [];
        foreach ((new \ReflectionClass($class))->getMethods(\ReflectionMethod::IS_PUBLIC) as $method) {
            if ($method->getDeclaringClass()->getName() === $class && !$method->isStatic()) {
                $methods[] = $method;
            }
        }

        return $methods;
    }

    /**
     * @param class-string $attribute
     *
     * @return null|array<string, mixed>
     */
    private function args(\ReflectionMethod $method, string $attribute): ?array
    {
        $found = $method->getAttributes($attribute);
        if ($found === []) {
            return null;
        }

        return $found[0]->getArguments();
    }

    private function meta(\ReflectionMethod $method): ?ToolMeta
    {
        $found = $method->getAttributes(ToolMeta::class);
        if ($found === []) {
            return null;
        }

        $meta = $found[0]->newInstance();

        return $meta instanceof ToolMeta ? $meta : null;
    }
}
