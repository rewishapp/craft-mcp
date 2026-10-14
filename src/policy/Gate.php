<?php

namespace rewish\craft\mcp\policy;

use rewish\craft\mcp\attributes\ToolMeta;

/**
 * Decides whether a tool is advertised and callable for this connection.
 */
final class Gate
{
    /**
     * @param list<string> $disabled
     */
    public function __construct(
        private readonly bool $dangerousEnabled,
        private readonly array $disabled,
        private readonly ?string $scope,
    ) {}

    public function allows(string $name, ToolMeta $meta): bool
    {
        if (\in_array($name, $this->disabled, true)) {
            return false;
        }

        if ($meta->dangerous && !$this->dangerousEnabled) {
            return false;
        }

        if ($this->scope === null || $this->scope === 'full') {
            return true;
        }

        if ($this->scope === 'readonly') {
            return $meta->minScope === 'readonly' && !$meta->dangerous;
        }

        if ($this->scope === 'content') {
            if ($meta->minScope === 'full') {
                return false;
            }

            return !$meta->dangerous || $meta->minScope === 'content';
        }

        return false;
    }
}
