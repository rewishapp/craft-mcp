<?php

namespace rewish\craft\mcp\http\oauth;

/**
 * A single-use authorization code. Stored by hash only; the plaintext goes
 * once to the redirect URI.
 */
final readonly class AuthorizationCode
{
    public function __construct(
        public int $grantId,
        public string $redirectUri,
        public string $codeChallenge,
        public \DateTimeImmutable $expiryDate,
        public ?\DateTimeImmutable $dateUsed = null,
    ) {}
}
