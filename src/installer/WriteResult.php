<?php

namespace rewish\craft\mcp\installer;

final class WriteResult
{
    public function __construct(
        public readonly string $path,
        public readonly bool $created,
        public readonly bool $updated,
        public readonly bool $serverExisted,
    ) {}

    public static function created(string $path): self
    {
        return new self($path, true, false, false);
    }

    public static function added(string $path): self
    {
        return new self($path, false, true, false);
    }

    public static function overwritten(string $path): self
    {
        return new self($path, false, true, true);
    }

    public function getDescription(): string
    {
        if ($this->created) {
            return 'Created';
        }

        return $this->serverExisted ? 'Updated (overwritten)' : 'Updated';
    }
}
