<?php

namespace rewish\craft\mcp\tests;

use Mcp\Capability\Attribute\McpPrompt;
use Mcp\Capability\Attribute\McpResource;
use Mcp\Capability\Attribute\McpResourceTemplate;
use Mcp\Capability\Attribute\McpTool;
use Mcp\Server;
use PHPUnit\Framework\TestCase;
use rewish\craft\mcp\attributes\ToolMeta;

/**
 * @coversNothing
 */
final class ServerBuildTest extends TestCase
{
    public function testSdkAcceptsEveryHandler(): void
    {
        $builder = Server::builder()
            ->setServerInfo(name: 'MCP - All in one', version: '1.0.0', title: 'MCP - All in one')
            ->setInstructions('Test build.')
            ->setLazyLoading(false)
        ;

        $tools = 0;
        $prompts = 0;
        $resources = 0;

        foreach ($this->classes('tools') as $class) {
            foreach ($this->methods($class) as $method) {
                if ($method->getAttributes(McpTool::class) === [] || $method->getAttributes(ToolMeta::class) === []) {
                    continue;
                }
                $builder->addTool([$class, $method->getName()]);
                ++$tools;
            }
        }

        foreach ($this->classes('prompts') as $class) {
            foreach ($this->methods($class) as $method) {
                if ($method->getAttributes(McpPrompt::class) === []) {
                    continue;
                }
                $builder->addPrompt([$class, $method->getName()]);
                ++$prompts;
            }
        }

        foreach ($this->classes('resources') as $class) {
            foreach ($this->methods($class) as $method) {
                $resource = $method->getAttributes(McpResource::class)[0] ?? null;
                if ($resource !== null) {
                    $builder->addResource([$class, $method->getName()], (string) $resource->getArguments()['uri']);
                    ++$resources;

                    continue;
                }
                $template = $method->getAttributes(McpResourceTemplate::class)[0] ?? null;
                if ($template === null) {
                    continue;
                }
                $builder->addResourceTemplate([$class, $method->getName()], (string) $template->getArguments()['uriTemplate']);
                ++$resources;
            }
        }

        $server = $builder->build();

        self::assertInstanceOf(Server::class, $server);
        self::assertGreaterThanOrEqual(70, $tools);
        self::assertSame(10, $prompts);
        self::assertSame(13, $resources);
        self::assertContains('reload_mcp', $this->toolNames());
    }

    /**
     * @return list<class-string>
     */
    private function classes(string $directory): array
    {
        $classes = [];
        foreach (glob(\dirname(__DIR__)."/src/{$directory}/*.php") ?: [] as $file) {
            $classes[] = 'rewish\craft\mcp\\'.$directory.'\\'.basename($file, '.php');
        }

        return $classes;
    }

    /**
     * @param class-string $class
     *
     * @return list<\ReflectionMethod>
     */
    private function methods(string $class): array
    {
        $methods = [];
        foreach ((new \ReflectionClass($class))->getMethods(\ReflectionMethod::IS_PUBLIC) as $method) {
            if ($method->getDeclaringClass()->getName() === $class && !$method->isStatic()) {
                $methods[] = $method;
            }
        }

        return $methods;
    }

    /**
     * @return list<string>
     */
    private function toolNames(): array
    {
        $names = [];
        foreach ($this->classes('tools') as $class) {
            foreach ($this->methods($class) as $method) {
                $tool = $method->getAttributes(McpTool::class)[0] ?? null;
                if ($tool === null) {
                    continue;
                }
                $names[] = (string) $tool->getArguments()['name'];
            }
        }

        return $names;
    }
}
