<?php

namespace rewish\craft\mcp\http\oauth;

use rewish\craft\mcp\services\Tokens;

/**
 * Revoking a connected app: the grant is marked revoked, which stops its
 * refresh tokens and unused codes from ever being exchanged, and its access
 * tokens are deleted, which stops the app on its very next request.
 */
final readonly class Grants
{
    public function __construct(
        private OAuthStore $store,
        private Tokens $tokens,
        private ?\Closure $now = null,
    ) {}

    public function revoke(int $grantId): void
    {
        $this->store->revokeGrant($grantId, $this->now !== null ? ($this->now)() : new \DateTimeImmutable());
        $this->tokens->revokeGrant($grantId);
    }
}
