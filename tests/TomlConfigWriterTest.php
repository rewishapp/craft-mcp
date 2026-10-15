<?php

namespace rewish\craft\mcp\tests;

use PHPUnit\Framework\TestCase;
use rewish\craft\mcp\installer\TomlConfigWriter;

/**
 * @coversNothing
 */
final class TomlConfigWriterTest extends TestCase
{
    private string $path;

    protected function setUp(): void
    {
        $this->path = sys_get_temp_dir().'/craft-mcp-codex-'.uniqid('', true).'.toml';
    }

    protected function tearDown(): void
    {
        if (is_file($this->path)) {
            unlink($this->path);
        }
    }

    public function testWritesAndReplacesOnlyOwnTable(): void
    {
        file_put_contents($this->path, "model = \"o3\"\n\n[mcp_servers.other]\ncommand = \"keep\"\nargs = [\"me\"]\n");

        $writer = new TomlConfigWriter();
        $writer->write($this->path, 'craft-mcp', [
            'command' => 'ddev',
            'args' => ['exec', 'php', 'vendor/bin/mcp-server'],
        ]);

        $content = (string) file_get_contents($this->path);
        self::assertStringContainsString('model = "o3"', $content);
        self::assertStringContainsString('[mcp_servers.other]', $content);
        self::assertStringContainsString('command = "keep"', $content);
        self::assertStringContainsString('[mcp_servers.craft-mcp]', $content);
        self::assertStringContainsString('command = "ddev"', $content);
        self::assertStringContainsString('"vendor/bin/mcp-server"', $content);

        $writer->write($this->path, 'craft-mcp', [
            'command' => 'php',
            'args' => ['vendor/bin/mcp-server'],
        ]);
        $content = (string) file_get_contents($this->path);
        self::assertSame(1, substr_count($content, '[mcp_servers.craft-mcp]'));
        self::assertStringContainsString('command = "php"', $content);
        self::assertStringNotContainsString('command = "ddev"', $content);
    }

    public function testReplacesOwnSubtablesAndQuotedNames(): void
    {
        file_put_contents(
            $this->path,
            "[mcp_servers.craft-mcp]\ncommand = \"old\"\n\n[mcp_servers.craft-mcp.env]\nFOO = \"bar\"\n\n[mcp_servers.other]\ncommand = \"keep\"\n",
        );

        $writer = new TomlConfigWriter();
        $writer->write($this->path, 'craft-mcp', [
            'command' => 'php',
            'args' => ['vendor/bin/mcp-server'],
            'cwd' => '/tmp/project',
        ]);

        $content = (string) file_get_contents($this->path);
        self::assertStringContainsString('[mcp_servers.other]', $content);
        self::assertStringContainsString('command = "keep"', $content);
        self::assertStringContainsString('cwd = "/tmp/project"', $content);
        self::assertStringNotContainsString('[mcp_servers.craft-mcp.env]', $content);
        self::assertStringNotContainsString('FOO = "bar"', $content);
        self::assertTrue($writer->serverExists($this->path, 'craft-mcp'));
    }
}
