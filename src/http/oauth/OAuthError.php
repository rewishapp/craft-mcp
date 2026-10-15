<?php

namespace rewish\craft\mcp\http\oauth;

/**
 * An OAuth error as RFC 6749 spells it: a fixed code the client acts on, a
 * description for the person reading logs, and the HTTP status to send.
 */
final class OAuthError extends \RuntimeException
{
    public function __construct(
        public readonly string $error,
        string $description,
        public readonly int $status = 400,
    ) {
        parent::__construct($description);
    }

    public static function invalidRequest(string $description): self
    {
        return new self('invalid_request', $description);
    }

    public static function invalidGrant(string $description): self
    {
        return new self('invalid_grant', $description);
    }

    public static function invalidClient(string $description): self
    {
        return new self('invalid_client', $description, 401);
    }

    /**
     * @return array{error: string, error_description: string}
     */
    public function toArray(): array
    {
        return ['error' => $this->error, 'error_description' => $this->getMessage()];
    }
}
