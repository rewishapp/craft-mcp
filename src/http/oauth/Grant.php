<?php

namespace rewish\craft\mcp\http\oauth;

use rewish\craft\mcp\enums\Scope;

/**
 * One user's consent for one app at one scope: what the user sees as a
 * connected app, and what revoking takes away (its codes, refresh tokens and
 * access tokens together).
 */
final readonly class Grant
{
    public function __construct(
        public int $clientRecordId,
        public int $userId,
        public Scope $scope,
        public ?int $id = null,
        public ?\DateTimeImmutable $dateCreated = null,
        public ?\DateTimeImmutable $dateRevoked = null,
    ) {
    }

    public function isRevoked(): bool
    {
        return $this->dateRevoked !== null;
    }
}
