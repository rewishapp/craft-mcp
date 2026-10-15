<?php

namespace rewish\craft\mcp\http\fetch;

/**
 * A response body as it arrives, kept to one byte past the cap: enough to
 * know the file is too large without holding the rest of it.
 */
final class CappedBody
{
    private string $bytes = '';

    public function __construct(
        private readonly int $maxBytes,
    ) {}

    public function append(string $chunk): void
    {
        $this->bytes .= substr($chunk, 0, max(0, $this->maxBytes + 1 - \strlen($this->bytes)));
    }

    public function exceeded(): bool
    {
        return \strlen($this->bytes) > $this->maxBytes;
    }

    public function bytes(): string
    {
        return $this->bytes;
    }
}
