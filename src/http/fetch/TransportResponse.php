<?php

namespace rewish\craft\mcp\http\fetch;

/**
 * What the transport brought back: the status, the content type, and the
 * body, which the transport stops reading one byte past the cap.
 */
final readonly class TransportResponse
{
    public function __construct(
        public int $status,
        public ?string $contentType,
        public string $body,
    ) {}
}
