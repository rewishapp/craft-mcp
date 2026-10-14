<?php

namespace rewish\craft\mcp\tests;

use PHPUnit\Framework\TestCase;
use rewish\craft\mcp\attributes\ToolMeta;
use rewish\craft\mcp\policy\Gate;

/**
 * @coversNothing
 */
final class GateTest extends TestCase
{
    public function testReadonlyHidesWrites(): void
    {
        $gate = new Gate(true, [], 'readonly');

        self::assertTrue($gate->allows('list_entries', new ToolMeta('content')));
        self::assertFalse($gate->allows('create_entry', new ToolMeta('content', 'content', true)));
        self::assertFalse($gate->allows('get_config', new ToolMeta('system', 'full')));
    }

    public function testContentScopeCanWriteEntriesWhenDangerousToolsAreOn(): void
    {
        $on = new Gate(true, [], 'content');
        $off = new Gate(false, [], 'content');
        $write = new ToolMeta('content', 'content', true);

        self::assertTrue($on->allows('create_entry', $write));
        self::assertFalse($off->allows('create_entry', $write));
        self::assertFalse($on->allows('run_query', new ToolMeta('database', 'full', true)));
    }

    public function testDisabledToolsStayHidden(): void
    {
        $gate = new Gate(true, ['tinker'], null);

        self::assertFalse($gate->allows('tinker', new ToolMeta('debug', 'full', true)));
    }
}
