<?php

namespace rewish\craft\mcp\attributes;

/**
 * Policy metadata for an MCP tool. The registrar reads this when deciding
 * whether a token scope is allowed to see the tool.
 */
#[\Attribute(\Attribute::TARGET_METHOD)]
final class ToolMeta
{
    public function __construct(
        public string $category,
        public string $minScope = 'readonly',
        public bool $dangerous = false,
    ) {}
}
