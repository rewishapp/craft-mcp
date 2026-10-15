<?php

namespace rewish\craft\mcp\http\oauth;

/**
 * A refresh token. Used once: refreshing marks it used and issues the next
 * one, so a second use means the token was copied, and the grant is revoked.
 */
final readonly class RefreshToken
{
    public function __construct(
        public int $grantId,
        public \DateTimeImmutable $expiryDate,
        public ?\DateTimeImmutable $dateUsed = null,
    ) {
    }
}
